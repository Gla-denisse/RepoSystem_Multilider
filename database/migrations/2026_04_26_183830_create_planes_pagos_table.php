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
        Schema::create('planes_pagos', function (Blueprint $table) {
            $table->id();
            
            // Llave foránea hacia la Venta
            $table->foreignId('nota_venta_id')->unique()->constrained('notas_ventas')->onDelete('cascade');
            
            $table->decimal('monto', 12, 2); // Saldo a financiar
            $table->integer('numero_cuotas');
            $table->date('fecha_inicio');
            $table->date('fecha_final');
            $table->string('plazo', 50); // Ej: "60 Meses", "5 Años"
            $table->decimal('tasa_interes', 5, 2)->default(0); // Porcentaje %
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planes_pagos');
    }
};
