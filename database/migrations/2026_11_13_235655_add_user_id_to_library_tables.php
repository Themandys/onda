<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de biblioteca y las columnas que, junto al usuario, identifican cada fila.
     *
     * @var array<string, list<string>>
     */
    private const TABLES = [
        'favorites' => ['video_id'],
        'plays' => ['video_id'],
        'excluded_tracks' => ['video_id'],
        'offline_tracks' => ['video_id'],
        'monthly_plays' => ['year_month', 'video_id'],
        'playlists' => [],
    ];

    /**
     * La biblioteca pasa a ser de cada cuenta. Lo que ya había guardado
     * queda a nombre de la primera cuenta, que era la única.
     */
    public function up(): void
    {
        $ownerId = DB::table('users')->min('id');

        foreach (self::TABLES as $name => $key) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->index()->constrained()->cascadeOnDelete();
            });

            DB::table($name)->update(['user_id' => $ownerId]);

            if ($key !== []) {
                Schema::table($name, function (Blueprint $table) use ($key): void {
                    $table->dropUnique($key);
                    $table->unique(['user_id', ...$key]);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name => $key) {
            if ($key !== []) {
                Schema::table($name, function (Blueprint $table) use ($key): void {
                    $table->dropUnique(['user_id', ...$key]);
                    $table->unique($key);
                });
            }

            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
