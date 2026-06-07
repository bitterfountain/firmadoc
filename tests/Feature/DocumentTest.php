<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** PDF minimo valido (la cabecera %PDF basta para el detector de MIME). */
    private function pdfBytes(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    public function test_documents_index_loads(): void
    {
        $this->get(route('documents.index'))->assertOk()->assertSee('Subir documento');
    }

    public function test_home_redirects_authenticated_to_documents(): void
    {
        $this->get('/')->assertRedirect(route('documents.index'));
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
            'user_id' => $this->user->id,
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
            'user_id' => $this->user->id,
        ]);

        Storage::fake('local');
        $this->get(route('documents.pdf', $doc))->assertNotFound();
    }

    public function test_document_can_be_deleted(): void
    {
        Storage::fake('local');
        $doc = Document::create([
            'user_id' => $this->user->id,
            'original_name' => 'x.pdf',
            'source_format' => 'pdf',
            'status' => 'ready',
        ]);

        $this->delete(route('documents.destroy', $doc))
            ->assertRedirect(route('documents.index'));

        $this->assertDatabaseMissing('documents', ['id' => $doc->id]);
    }

    public function test_cannot_access_other_users_document(): void
    {
        Storage::fake('local');
        $other = User::factory()->create();
        $doc = Document::create([
            'user_id' => $other->id,
            'original_name' => 'ajeno.pdf',
            'source_format' => 'pdf',
            'status' => 'ready',
            'pdf_path' => 'documents/1/normalized.pdf',
        ]);

        // Autenticado como $this->user (de setUp), no es el dueño -> 403.
        $this->get(route('documents.sign', $doc))->assertForbidden();
        $this->get(route('documents.audit', $doc))->assertForbidden();
        $this->get(route('documents.pdf', $doc))->assertForbidden();
        $this->get(route('documents.download', $doc))->assertForbidden();
        $this->delete(route('documents.destroy', $doc))->assertForbidden();

        // Y no aparece en su listado.
        $this->get(route('documents.index'))->assertOk()->assertDontSee('ajeno.pdf');
    }
}
