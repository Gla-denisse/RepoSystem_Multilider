<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_pago_cuenta_default', function (Blueprint $table) {
            $table->id();

            // Relaciones
            $table->foreignId('mi_empresa_id')->constrained('mi_empresa')->onDelete('cascade');
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->onDelete('cascade');
            $table->foreignId('cuenta_bancaria_id')->constrained('cuentas_bancarias')->onDelete('cascade');

            // Esta combinación es única por empresa
            $table->unique(['mi_empresa_id', 'metodo_pago_id']);

            $table->timestamps();

            // Índices
            $table->index('mi_empresa_id');
            $table->index('metodo_pago_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_pago_cuenta_default');
    }
};
