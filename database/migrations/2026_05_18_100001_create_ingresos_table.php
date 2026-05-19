<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingresos', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');
            $table->string('concepto', 255);
            $table->enum('categoria', [
                'VENTA_CONTADO',
                'CUOTA_INICIAL',
                'CUOTA',
                'OTRO',
            ]);
            $table->decimal('monto', 12, 2);
            $table->enum('moneda', ['Bs', '$'])->default('Bs');
            $table->enum('origen', ['AUTOMATICO', 'MANUAL'])->default('MANUAL');

            // Trazabilidad (nulos si es ingreso manual sin relación a venta)
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->onDelete('set null');
            $table->foreignId('nota_venta_id')->nullable()->constrained('notas_ventas')->onDelete('set null');
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('comprobante', 500)->nullable();
            $table->text('observaciones')->nullable();
            $table->enum('estado', ['CONFIRMADO', 'ANULADO'])->default('CONFIRMADO');

            $table->timestamps();

            $table->index('fecha');
            $table->index('categoria');
            $table->index('estado');
            $table->index('origen');
            $table->index('nota_venta_id');
            $table->index('cuenta_bancaria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingresos');
    }
};
