<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Vigencia de la cuenta profesional. NULL = sin caducidad (admin/cuentas base).
            $table->timestamp('pro_until')->nullable()->after('email');
            // Quien puede generar invitaciones.
            $table->boolean('is_admin')->default(false)->after('pro_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pro_until', 'is_admin']);
        });
    }
};
