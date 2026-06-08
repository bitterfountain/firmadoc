<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Certificado .p12/.pfx del usuario para firmar (PAdES pkcs12).
            // El binario y la contraseña se guardan cifrados (cast 'encrypted').
            $table->text('signing_cert')->nullable()->after('is_admin');          // base64 del .p12, cifrado
            $table->text('signing_cert_password')->nullable()->after('signing_cert'); // cifrado
            $table->string('signing_cert_subject')->nullable()->after('signing_cert_password');
            $table->string('signing_cert_name')->nullable()->after('signing_cert_subject');
            $table->date('signing_cert_expires_at')->nullable()->after('signing_cert_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'signing_cert', 'signing_cert_password', 'signing_cert_subject',
                'signing_cert_name', 'signing_cert_expires_at',
            ]);
        });
    }
};
