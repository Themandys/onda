<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar')->default('')->after('email');
            $table->string('bio', 160)->default('')->after('avatar');
            $table->string('theme', 32)->default('piedra')->after('bio');
        });

        Schema::create('monthly_plays', function (Blueprint $table) {
            $table->id();
            $table->string('year_month', 7)->index();
            $table->string('video_id', 16);
            $table->string('title');
            $table->string('artist')->default('');
            $table->string('thumbnail_url', 2048)->default('');
            $table->unsignedInteger('duration')->default(210);
            $table->unsignedInteger('play_count')->default(0);
            $table->timestamp('last_played_at')->index();
            $table->timestamps();

            $table->unique(['year_month', 'video_id']);
        });

        Schema::create('offline_tracks', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 16)->unique();
            $table->string('title');
            $table->string('artist')->default('');
            $table->string('thumbnail_url', 2048)->default('');
            $table->unsignedInteger('duration')->default(0);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('mime_type', 64)->default('audio/webm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_tracks');
        Schema::dropIfExists('monthly_plays');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'bio', 'theme']);
        });
    }
};
