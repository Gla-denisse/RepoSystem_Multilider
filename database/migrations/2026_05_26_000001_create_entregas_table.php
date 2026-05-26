<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->unique()->constrained('contratos')->onDelete('restrict');
            $table->date('fecha_programada');
            $table->date('fecha_entrega')->nullable();
            $table->string('estado', 50)->default('Pendiente'); // Pendiente | Entregado | Diferido
            $table->string('condicion_inmueble', 100)->nullable();
            $table->text('items_entregados')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('entregado_por', 150)->nullable();
            $table->string('recibido_por', 150)->nullable();
            $table->text('url_acta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
