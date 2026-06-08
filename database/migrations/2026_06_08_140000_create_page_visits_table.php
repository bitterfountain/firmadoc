<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45);
            $table->char('country_code', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('url', 500);
            $table->string('page_type', 30)->index();
            $table->string('user_agent', 500)->nullable();
            $table->string('referer', 500)->nullable();
            $table->timestamp('visited_at')->useCurrent();

            $table->index(['visited_at', 'page_type']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
