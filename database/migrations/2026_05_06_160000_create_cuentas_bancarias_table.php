<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuentas_bancarias', function (Blueprint $table) {
            $table->id();

            // Relación con empresa
            $table->foreignId('mi_empresa_id')->constrained('mi_empresa')->onDelete('cascade');

            // Información básica
            $table->string('nombre', 255); // "Caja Principal", "Cuenta BCP", "Stripe", etc.
            $table->enum('tipo', ['EFECTIVO', 'BANCARIA', 'DIGITAL', 'OTRA'])->default('BANCARIA');
            $table->text('descripcion')->nullable();

            // Para cuentas bancarias
            $table->string('banco', 100)->nullable();
            $table->string('numero_cuenta', 50)->nullable();
            $table->string('titular', 255)->nullable();
            $table->string('iban', 50)->nullable();

            // Para cuentas digitales/online
            $table->string('proveedor', 100)->nullable(); // "Stripe", "PayPal", "Mercado Pago"
            $table->string('codigo_integracion', 255)->nullable(); // API key, webhook ID

            // Control
            $table->decimal('saldo_inicial', 12, 2)->default(0);
            $table->enum('estado', ['Activa', 'Inactiva'])->default('Activa');

            $table->timestamps();

            // Índices
            $table->index('mi_empresa_id');
            $table->index('tipo');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_bancarias');
    }
};
