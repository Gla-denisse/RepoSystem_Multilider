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
        Schema::create('notas_ventas', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('asesor_id')->constrained('asesores');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('propiedad_id')->constrained('propiedades');

            // Datos Base de la Nota
            $table->date('fecha');
            $table->decimal('monto_total', 12, 2);
            $table->decimal('monto_comision', 12, 2)->nullable();
            $table->enum('tipo_venta', ['CONTADO', 'CREDITO']);

            // Campos específicos para VENTA AL CONTADO
            $table->decimal('descuento', 12, 2)->nullable();
            $table->decimal('monto_liquido', 12, 2)->nullable();

            // Campos específicos para VENTA AL CRÉDITO
            $table->decimal('cuota_inicial', 12, 2)->nullable();
            $table->decimal('saldo_credito', 12, 2)->nullable();

            // Estado (Ej: 'Completada', 'Anulada')
            $table->string('estado', 50)->default('Completada');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas_ventas');
    }
};
