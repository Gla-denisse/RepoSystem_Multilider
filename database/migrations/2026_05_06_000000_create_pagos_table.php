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
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('nota_venta_id')->constrained('notas_ventas')->onDelete('cascade');
            $table->foreignId('cuota_id')->nullable()->constrained('cuotas')->onDelete('cascade');

            // Atributos solicitados
            $table->enum('concepto_pago', ['CUOTA_INICIAL', 'CUOTA', 'VENTA_CONTADO', 'OTRO']);
            $table->date('fecha_pago');
            $table->decimal('monto', 12, 2);

            // Metadata
            $table->string('estado', 50)->default('Registrado'); // Registrado, Cancelado, Rechazado
            $table->text('observaciones')->nullable();

            $table->timestamps();

            // Índices para mejores búsquedas
            $table->index('nota_venta_id');
            $table->index('cuota_id');
            $table->index('fecha_pago');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
