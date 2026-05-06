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
            // Relaciones
            $table->foreignId('propietario_id')->constrained('propietarios');
            $table->foreignId('zona_id')->constrained('zonas'); // 🌟 Conectado a la nueva tabla Zonas
            $table->foreignId('ubicacion_id')->nullable()->constrained('ubicaciones')->nullOnDelete();
            
            // Datos Identificativos
            $table->string('codigo')->unique(); // Ej: VILLA-01
            $table->string('tipo'); // Casa, Lote, Local, etc.
            
            // Datos Económicos
            $table->decimal('precio_venta', 12, 2);
            $table->enum('moneda', ['USD', 'BOB'])->default('USD');
            
            // Medidas y Superficies
            $table->decimal('superficie_m2', 10, 2); // Terreno total
            $table->decimal('superficie_construida_m2', 10, 2)->nullable(); // Solo para casas
            $table->decimal('frente_mts', 8, 2)->nullable();
            $table->decimal('fondo_mts', 8, 2)->nullable();
            
            // Características Físicas
            $table->integer('habitaciones')->nullable();
            $table->integer('banos')->nullable();
            $table->boolean('es_esquina')->default(false);

            // Ubicación y Colindancias
            $table->string('direccion')->nullable();
            $table->string('nro_lote')->nullable();
            $table->string('colinda_norte')->nullable();
            $table->string('colinda_sur')->nullable();
            $table->string('colinda_este')->nullable();
            $table->string('colinda_oeste')->nullable();
            
            // Estados del Sistema
            $table->string('estado')->default('Disponible'); // Disponible, Vendido, Reservado
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedades');
    }
};