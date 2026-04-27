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
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('plan_pago_id')->constrained('planes_pagos')->onDelete('cascade');
            
            $table->integer('numero_cuota');
            $table->date('fecha_vencimiento');
            $table->decimal('monto_cuota', 12, 2); // Capital + Interés
            $table->decimal('monto_interes', 12, 2)->default(0);
            $table->decimal('monto_capital', 12, 2);
            $table->decimal('saldo_capital', 12, 2)->default(0);
            $table->string('estado', 50)->default('Pendiente'); // Pendiente, Pagada, Vencida
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cuotas');
    }
};
