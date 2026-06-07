<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    /** PDF minimo valido (la cabecera %PDF basta para el detector de MIME). */
    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    public function test_home_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee('Subir documento');
    }

    public function test_upload_pdf_creates_and_normalizes_document(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->createWithContent('contrato.pdf', $this->pdfBytes());

        $response = $this->post(route('documents.store'), ['file' => $file]);

        $doc = Document::first();
        $this->assertNotNull($doc);
        $this->assertSame('contrato.pdf', $doc->original_name);
        $this->assertSame('ready', $doc->status);
        $this->assertSame('pdf', $doc->source_format);
        Storage::disk('local')->assertExists($doc->pdf_path);
        $response->assertRedirect(route('documents.sign', $doc));
    }

    public function test_upload_rejects_unsupported_format(): void
    {
        Storage::fake('local');
        $file = UploadedFile::fake()->create('malware.exe', 10);

        $this->post(route('documents.store'), ['file' => $file])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_sign_page_requires_ready_document(): void
    {
        $doc = Document::create([
            'original_name' => 'x.pdf',
            'source_format' => 'pdf',
            'status' => 'failed',
        ]);

        $this->get(route('documents.sign', $doc))
            ->assertRedirect(route('documents.index'));
    }

    public function test_pdf_stream_404_when_missing(): void
    {
        $doc = Document::create([
            'original_name' => 'x.pdf',
            'source_format' => 'pdf',
            'status' => 'ready',
            'pdf_path' => 'documents/999/normalized.pdf',
        ]);

        Storage::fake('local');
        $this->get(route('documents.pdf', $doc))->assertNotFound();
    }

    public function test_document_can_be_deleted(): void
    {
        Storage::fake('local');
        $doc = Document::create([
            'original_name' => 'x.pdf',
            'source_format' => 'pdf',
            'status' => 'ready',
        ]);

        $this->delete(route('documents.destroy', $doc))
            ->assertRedirect(route('documents.index'));

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    }
}
