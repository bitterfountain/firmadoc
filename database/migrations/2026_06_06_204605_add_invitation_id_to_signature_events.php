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
            // Vincula el evento con la invitacion (si vino de un flujo multi-firmante).
            $table->foreignId('invitation_id')->nullable()->after('document_id')
                ->constrained('signature_invitations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signature_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invitation_id');
        });
    }
};
