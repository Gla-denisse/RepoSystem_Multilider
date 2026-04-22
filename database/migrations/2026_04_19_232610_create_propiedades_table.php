<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedades', function (Blueprint $table) {
            $table->id();
            
            // Relaciones (Llaves Foráneas)
            $table->foreignId('propietario_id')->constrained('propietarios')->restrictOnDelete();
            $table->foreignId('manzano_id')->constrained('manzanos')->restrictOnDelete();
            
            // Relación 1 a 1 (Una ubicación pertenece a una sola propiedad)
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            $table->unique('ubicacion_id'); 

            // Datos de la Propiedad
            $table->string('tipo', 100); // Lote, Casa, Terreno, etc.
            $table->string('codigo', 100)->unique();
            $table->decimal('precio_venta', 12, 2);
            $table->string('direccion')->nullable();
            $table->string('nro_lote', 50)->nullable();
            $table->decimal('superficie_m2', 10, 2);

            // Colindancias
            $table->string('colinda_norte')->nullable();
            $table->string('colinda_sur')->nullable();
            $table->string('colinda_este')->nullable();
            $table->string('colinda_oeste')->nullable();

            // Estado de la propiedad
            $table->string('estado', 50)->default('Disponible'); // Disponible, Vendido, Reservado
            $table->boolean('activo')->default(true);// para activo/desactivado

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedades');
    }
};