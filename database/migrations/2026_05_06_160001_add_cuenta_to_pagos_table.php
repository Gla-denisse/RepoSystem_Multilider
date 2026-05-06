<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('cuenta_id')->nullable()->after('metodo_pago_id')->constrained('cuentas_bancarias')->onDelete('set null');
            $table->index('cuenta_id');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeignKey(['cuenta_id']);
            $table->dropColumn('cuenta_id');
        });
    }
};
