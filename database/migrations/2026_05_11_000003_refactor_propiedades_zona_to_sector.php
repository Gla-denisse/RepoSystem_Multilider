<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            // Agregar sector_urbano_id (nullable primero para migración de datos legacy)
            $table->foreignId('sector_urbano_id')
                  ->nullable()
                  ->after('zona_id')
                  ->constrained('sectores_urbanos')
                  ->onDelete('cascade');
        });

        // Si existen datos legacy, crear un sector temporal y reasignar
        if (Schema::hasColumn('propiedades', 'zona_id')) {
            // Crear sector temporal por cada distrito existente
            DB::statement("
                INSERT INTO sectores_urbanos (distrito_id, nombre, tipo, estado, created_at, updated_at)
                SELECT DISTINCT d.id, CONCAT('Sector General - ', d.nombre), 'Barrio', 1, NOW(), NOW()
                FROM distritos d
                WHERE d.id IN (SELECT zona_id FROM propiedades WHERE zona_id IS NOT NULL)
            ");

            // Asignar el sector temporal a cada propiedad según su zona_id
            DB::statement("
                UPDATE propiedades p
                JOIN sectores_urbanos su ON su.distrito_id = p.zona_id
                SET p.sector_urbano_id = su.id
                WHERE p.zona_id IS NOT NULL AND p.sector_urbano_id IS NULL
            ");
        }

        Schema::table('propiedades', function (Blueprint $table) {
            // Eliminar FK y columna zona_id
            $table->dropForeign(['zona_id']);
            $table->dropColumn('zona_id');

            // Hacer sector_urbano_id obligatorio
            $table->unsignedBigInteger('sector_urbano_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropForeign(['sector_urbano_id']);
            $table->dropColumn('sector_urbano_id');
            $table->foreignId('zona_id')->nullable()->constrained('distritos');
        });
    }
};
