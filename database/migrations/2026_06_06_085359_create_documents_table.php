<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            // Formato del archivo subido: pdf | docx | jpg | png
            $table->string('source_format', 10);
            // Ruta (en el disco privado) del PDF normalizado, listo para firmar.
            $table->string('pdf_path')->nullable();
            // Ruta del PDF ya firmado.
            $table->string('signed_path')->nullable();
            // uploaded | ready | signed | failed
            $table->string('status', 20)->default('uploaded');
            // Mensaje de error si la conversion falla.
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
