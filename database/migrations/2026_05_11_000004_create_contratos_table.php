<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nota_venta_id')->constrained('notas_ventas')->onDelete('restrict');
            $table->string('codigo_contrato', 50)->unique();
            $table->date('fecha_emision');
            $table->date('fecha_firma')->nullable();
            $table->string('tipo_venta', 100);
            $table->text('url_doc')->nullable();
            $table->string('estado', 50)->default('Pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
