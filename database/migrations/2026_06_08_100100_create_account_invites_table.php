<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_invites', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            // Dias de cuenta profesional que concede al canjearse.
            $table->unsignedInteger('grant_days')->default(365);
            // El enlace deja de poder canjearse despues de esta fecha (NULL = sin caducidad de enlace).
            $table->timestamp('expires_at')->nullable();
            // Canje (uso unico).
            $table->timestamp('used_at')->nullable();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_invites');
    }
};
