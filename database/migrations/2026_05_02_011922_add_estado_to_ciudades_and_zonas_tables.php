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
        Schema::table('ciudades', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->after('departamento');
        });

        Schema::table('zonas', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->after('nombre');
        });

        Schema::table('caracteristicas', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->after('tipo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ciudades', function (Blueprint $table) {
            $table->dropColumn('estado');
        });

        Schema::table('zonas', function (Blueprint $table) {
            $table->dropColumn('estado');
        });

        Schema::table('caracteristicas', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
