<?php

namespace App\Http\Controllers;

use App\Concerns\HandlesDocumentFiles;
use App\Http\Requests\StoreDocumentRequest;
use App\Models\AccountInvite;
use App\Models\Document;
use App\Models\ProRequest;
use App\Services\PdfConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    use HandlesDocumentFiles;

    /** Listado + formulario de subida (solo los documentos del usuario). */
    public function index(): View
    {
        $documents = Document::where('user_id', auth()->id())
            ->withCount('signatureEvents')
            ->latest()
            ->get();

        $isAdmin = auth()->user()->is_admin;
        $invites = $isAdmin ? AccountInvite::latest()->take(10)->get() : collect();
        $proRequests = $isAdmin ? ProRequest::where('status', 'pending')->latest()->get() : collect();

        return view('documents.index', compact('documents', 'invites', 'proRequests'));
    }

    /** Aborta si el documento no pertenece al usuario autenticado. */
    private function authorizeOwner(Document $document): void
    {
        abort_unless($document->user_id === auth()->id(), 403);
    }

    /** Panel de auditoria: historial de eventos de firma de un documento. */
    public function audit(Document $document): View
    {
        $this->authorizeOwner($document);

        $events = $document->signatureEvents()->latest()->get();

        return view('documents.audit', compact('document', 'events'));
    }

    /** Sube un archivo y lo normaliza a PDF. */
    public function store(StoreDocumentRequest $request, PdfConversionService $converter): RedirectResponse
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        $document = Document::create([
            'user_id' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'source_format' => $ext,
            'status' => 'uploaded',
        ]);

        $dir = "documents/{$document->id}";
        $work = null;

        try {
            // El pipeline de LibreOffice necesita rutas locales: trabajamos en
            // un temporal y subimos original + normalizado al disco (s3/local).
            $work = $this->tempWorkDir();
            $sourceAbs = $work.DIRECTORY_SEPARATOR."original.{$ext}";
            copy($file->getRealPath(), $sourceAbs);

            $pdfAbs = $converter->normalizeToPdf($sourceAbs, $ext, $work);

            $this->pushFromLocal($sourceAbs, "{$dir}/original.{$ext}");
            $this->pushFromLocal($pdfAbs, "{$dir}/normalized.pdf");

            $document->update([
                'pdf_path' => "{$dir}/normalized.pdf",
                'status' => 'ready',
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $document->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return redirect()
                ->route('documents.index')
                ->with('error', __('No se pudo convertir el documento: :error', ['error' => $e->getMessage()]));
        } finally {
            $this->cleanupTemp($work);
        }

        return redirect()->route('documents.sign', $document);
    }

    /** Pantalla de firma de un documento ya convertido (o re-firma de uno firmado). */
    public function sign(Document $document): View|RedirectResponse
    {
        $this->authorizeOwner($document);

        // Se puede firmar mientras exista el PDF normalizado, este "ready" o ya "signed".
        if (! $document->pdf_path || ! Storage::disk($this->docDisk())->exists($document->pdf_path)) {
            return redirect()
                ->route('documents.index')
                ->with('error', __('Ese documento no está listo para firmar.'));
        }

        return view('documents.sign', compact('document'));
    }

    /** Devuelve el PDF normalizado (para previsualizar con PDF.js). */
    public function pdf(Document $document)
    {
        $this->authorizeOwner($document);

        abort_unless($document->pdf_path && Storage::disk($this->docDisk())->exists($document->pdf_path), 404);

        return Storage::disk($this->docDisk())->response($document->pdf_path, 'documento.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Descarga el PDF firmado (o el normalizado si aun no se firmo). */
    public function download(Document $document)
    {
        $this->authorizeOwner($document);

        $path = $document->signed_path ?? $document->pdf_path;
        abort_unless($path && Storage::disk($this->docDisk())->exists($path), 404);

        $name = pathinfo($document->original_name, PATHINFO_FILENAME) . '-firmado.pdf';

        return Storage::disk($this->docDisk())->download($path, $name);
    }

    /** Elimina el documento y sus archivos. */
    public function destroy(Document $document): RedirectResponse
    {
        $this->authorizeOwner($document);

        Storage::disk($this->docDisk())->deleteDirectory("documents/{$document->id}");
        $document->delete();

        return redirect()->route('documents.index')->with('status', __('Documento eliminado.'));
    }
}
