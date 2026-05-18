<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla pivot muchos-a-muchos
        Schema::create('propiedad_propietario', function (Blueprint $table) {
            $table->foreignId('propiedad_id')
                  ->constrained('propiedades')
                  ->cascadeOnDelete();
            $table->foreignId('propietario_id')
                  ->constrained('propietarios')
                  ->cascadeOnDelete();
            $table->primary(['propiedad_id', 'propietario_id']);
        });

        // 2. Migrar datos existentes (propietario_id → pivot)
        DB::table('propiedades')
            ->whereNotNull('propietario_id')
            ->get(['id', 'propietario_id'])
            ->each(fn($row) => DB::table('propiedad_propietario')->insert([
                'propiedad_id'   => $row->id,
                'propietario_id' => $row->propietario_id,
            ]));

        // 3. Eliminar columna propietario_id de propiedades
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropForeign(['propietario_id']);
            $table->dropColumn('propietario_id');
        });
    }

    public function down(): void
    {
        // Restaurar columna propietario_id (se recupera el primer propietario del pivot)
        Schema::table('propiedades', function (Blueprint $table) {
            $table->foreignId('propietario_id')
                  ->nullable()
                  ->constrained('propietarios')
                  ->nullOnDelete();
        });

        DB::table('propiedad_propietario')
            ->orderBy('propiedad_id')
            ->get()
            ->groupBy('propiedad_id')
            ->each(fn($rows, $propiedadId) =>
                DB::table('propiedades')
                    ->where('id', $propiedadId)
                    ->update(['propietario_id' => $rows->first()->propietario_id])
            );

        Schema::dropIfExists('propiedad_propietario');
    }
};
