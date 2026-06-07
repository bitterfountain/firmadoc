<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Models\Document;
use App\Services\PdfConversionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    private const DISK = 'local';

    /** Listado + formulario de subida. */
    public function index(): View
    {
        $documents = Document::withCount('signatureEvents')->latest()->get();

        return view('documents.index', compact('documents'));
    }

    /** Panel de auditoria: historial de eventos de firma de un documento. */
    public function audit(Document $document): View
    {
        $events = $document->signatureEvents()->latest()->get();

        return view('documents.audit', compact('document', 'events'));
    }

    /** Sube un archivo y lo normaliza a PDF. */
    public function store(StoreDocumentRequest $request, PdfConversionService $converter): RedirectResponse
    {
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        $document = Document::create([
            'original_name' => $file->getClientOriginalName(),
            'source_format' => $ext,
            'status' => 'uploaded',
        ]);

        $dir = "documents/{$document->id}";
        $file->storeAs($dir, "original.{$ext}", self::DISK);

        try {
            $sourceAbs = Storage::disk(self::DISK)->path("{$dir}/original.{$ext}");
            $outputDir = Storage::disk(self::DISK)->path($dir);

            $converter->normalizeToPdf($sourceAbs, $ext, $outputDir);

            $document->update([
                'pdf_path' => "{$dir}/normalized.pdf",
                'status' => 'ready',
                'error' => null,
            ]);
        } catch (Throwable $e) {
            $document->update(['status' => 'failed', 'error' => $e->getMessage()]);

            return redirect()
                ->route('documents.index')
                ->with('error', "No se pudo convertir el documento: {$e->getMessage()}");
        }

        return redirect()->route('documents.sign', $document);
    }

    /** Pantalla de firma de un documento ya convertido (o re-firma de uno firmado). */
    public function sign(Document $document): View|RedirectResponse
    {
        // Se puede firmar mientras exista el PDF normalizado, este "ready" o ya "signed".
        if (! $document->pdf_path || ! Storage::disk(self::DISK)->exists($document->pdf_path)) {
            return redirect()
                ->route('documents.index')
                ->with('error', 'Ese documento no esta listo para firmar.');
        }

        return view('documents.sign', compact('document'));
    }

    /** Devuelve el PDF normalizado (para previsualizar con PDF.js). */
    public function pdf(Document $document)
    {
        abort_unless($document->pdf_path && Storage::disk(self::DISK)->exists($document->pdf_path), 404);

        return Storage::disk(self::DISK)->response($document->pdf_path, 'documento.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /** Descarga el PDF firmado (o el normalizado si aun no se firmo). */
    public function download(Document $document)
    {
        $path = $document->signed_path ?? $document->pdf_path;
        abort_unless($path && Storage::disk(self::DISK)->exists($path), 404);

        $name = pathinfo($document->original_name, PATHINFO_FILENAME) . '-firmado.pdf';

        return Storage::disk(self::DISK)->download($path, $name);
    }

    /** Elimina el documento y sus archivos. */
    public function destroy(Document $document): RedirectResponse
    {
        Storage::disk(self::DISK)->deleteDirectory("documents/{$document->id}");
        $document->delete();

        return redirect()->route('documents.index')->with('status', 'Documento eliminado.');
    }
}
