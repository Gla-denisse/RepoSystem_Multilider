<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reprogramaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_pago_id')->constrained('planes_pagos')->onDelete('cascade');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('motivo');
            $table->date('fecha_reprogramacion');
            $table->integer('cuota_desde');
            $table->decimal('saldo_capital_momento', 12, 2);
            $table->decimal('nueva_tasa_interes', 5, 2);
            $table->integer('nuevo_numero_cuotas');
            $table->date('nueva_fecha_inicio');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reprogramaciones');
    }
};
