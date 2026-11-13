<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La interfaz pasa a oscuro: «Grafito» es el tema por defecto y las
     * cuentas que ya existían se cambian a él (se puede volver a cualquier
     * otro desde el perfil).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 32)->default('grafito')->change();
        });

        DB::table('users')->update(['theme' => 'grafito']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('theme', 32)->default('piedra')->change();
        });
    }
};
