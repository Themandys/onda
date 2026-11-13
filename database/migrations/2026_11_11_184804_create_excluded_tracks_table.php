<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canciones excluidas: no vuelven a aparecer en el inicio, las
     * sugerencias ni los resultados de búsqueda hasta que se restauran.
     */
    public function up(): void
    {
        Schema::create('excluded_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 16)->unique();
            $table->string('title');
            $table->string('artist')->default('');
            $table->string('thumbnail_url', 2048)->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excluded_tracks');
    }
};
