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
        Schema::create('signature_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Identidad declarada por el firmante.
            $table->string('signer_name');
            $table->string('signer_email');

            // Evidencias de la sesion de firma.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            // OTP por email (se guarda hasheado, nunca en claro).
            $table->string('otp_hash');
            $table->timestamp('otp_expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();

            // Integridad: hash del PDF antes y despues de firmar.
            $table->char('original_sha256', 64)->nullable();
            $table->char('signed_sha256', 64)->nullable();

            // pending -> verified -> completed
            $table->string('status', 12)->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_events');
    }
};
