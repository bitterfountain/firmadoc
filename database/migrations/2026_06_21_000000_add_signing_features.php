<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Documents: signing mode, witness, webhook, allow guest-owned
        Schema::table('documents', function (Blueprint $table) {
            $table->string('signing_mode', 12)->default('sequential');
            $table->string('witness_name')->nullable();
            $table->string('witness_email')->nullable();
            $table->string('witness_token', 64)->nullable()->unique();
            $table->timestamp('witness_confirmed_at')->nullable();
            $table->string('webhook_url')->nullable();
        });
        // Allow guest (null) owner for quick multi-sign
        Schema::table('documents', function (Blueprint $table) {
            if (Schema::hasColumn('documents', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            }
        });

        // SignatureInvitations: lifecycle
        Schema::table('signature_invitations', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('last_reminded_at')->nullable();
        });

        // SignatureEvents: enhanced identity verification
        Schema::table('signature_events', function (Blueprint $table) {
            $table->string('verification_method', 20)->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('id_document_path')->nullable();
            $table->text('signing_cert')->nullable();
            $table->text('signing_cert_password')->nullable();
            $table->string('signing_cert_subject')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn([
                'signing_mode', 'witness_name', 'witness_email',
                'witness_token', 'witness_confirmed_at', 'webhook_url',
            ]);
        });

        Schema::table('signature_invitations', function (Blueprint $table) {
            $table->dropColumn(['expires_at', 'declined_at', 'last_reminded_at']);
        });

        Schema::table('signature_events', function (Blueprint $table) {
            $table->dropColumn([
                'verification_method', 'phone', 'phone_verified_at',
                'id_document_path', 'signing_cert', 'signing_cert_password',
                'signing_cert_subject',
            ]);
        });
    }
};
