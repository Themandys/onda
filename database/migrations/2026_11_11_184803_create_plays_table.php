<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de escucha: una fila por canción con la última vez que sonó.
     * Solo metadatos, igual que favoritos y playlists.
     */
    public function up(): void
    {
        Schema::create('plays', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 16)->unique();
            $table->string('title');
            $table->string('artist')->default('');
            $table->string('thumbnail_url', 2048)->default('');
            $table->unsignedInteger('play_count')->default(0);
            $table->timestamp('last_played_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plays');
    }
};
