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
        Schema::table('signature_events', function (Blueprint $table) {
            // ¿Se aplico el sellado criptografico PAdES (Nivel 2)?
            $table->boolean('pades_applied')->default(false)->after('signed_sha256');
        });
    }

    public function down(): void
    {
        Schema::table('signature_events', function (Blueprint $table) {
            $table->dropColumn('pades_applied');
        });
    }
};
