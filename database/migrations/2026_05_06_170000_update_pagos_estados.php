<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            // Cambiar el enum de estado para incluir PENDIENTE_PAGO
            $table->dropColumn('estado');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->enum('estado', ['PENDIENTE_PAGO', 'PAGADO', 'CANCELADO', 'RECHAZADO'])
                  ->default('PENDIENTE_PAGO')
                  ->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('estado');
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->string('estado', 50)->default('Registrado');
        });
    }
};
