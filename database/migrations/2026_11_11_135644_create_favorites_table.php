<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Favoritos: solo metadatos, nunca archivos. El audio se resuelve
     * en el momento de reproducir a partir de `video_id`.
     */
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
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
        Schema::dropIfExists('favorites');
    }
};
