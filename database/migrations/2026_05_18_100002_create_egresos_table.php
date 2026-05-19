<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egresos', function (Blueprint $table) {
            $table->id();

            $table->date('fecha');
            $table->string('concepto', 255);
            $table->enum('categoria', [
                'COMISION_ASESOR',
                'GASTO_ADMINISTRATIVO',
                'GASTO_OPERATIVO',
                'GASTO_MARKETING',
                'PAGO_PROPIETARIO',
                'OTRO',
            ]);
            $table->decimal('monto', 12, 2);
            $table->enum('moneda', ['Bs', '$'])->default('Bs');
            $table->enum('origen', ['AUTOMATICO', 'MANUAL'])->default('MANUAL');

            // Trazabilidad
            $table->foreignId('nota_venta_id')->nullable()->constrained('notas_ventas')->onDelete('set null');
            $table->foreignId('asesor_id')->nullable()->constrained('asesores')->onDelete('set null');
            $table->foreignId('cuenta_bancaria_id')->nullable()->constrained('cuentas_bancarias')->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->string('beneficiario', 255)->nullable();
            $table->string('comprobante', 500)->nullable();
            $table->text('observaciones')->nullable();

            // PENDIENTE = acumulado pero no desembolsado, PAGADO = dinero salió, ANULADO = cancelado
            $table->enum('estado', ['PENDIENTE', 'PAGADO', 'ANULADO'])->default('PENDIENTE');

            $table->timestamps();

            $table->index('fecha');
            $table->index('categoria');
            $table->index('estado');
            $table->index('origen');
            $table->index('nota_venta_id');
            $table->index('asesor_id');
            $table->index('cuenta_bancaria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egresos');
    }
};
