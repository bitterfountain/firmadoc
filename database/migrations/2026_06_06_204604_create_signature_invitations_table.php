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
        Schema::create('signature_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            // Token del enlace publico de firma.
            $table->string('token', 64)->unique();
            // Orden de firma (firma secuencial).
            $table->unsignedInteger('position')->default(1);
            // pending | signed
            $table->string('status', 12)->default('pending');
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signature_invitations');
    }
};
