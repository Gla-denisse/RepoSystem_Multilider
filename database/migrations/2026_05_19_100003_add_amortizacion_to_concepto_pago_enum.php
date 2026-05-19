<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pagos MODIFY COLUMN concepto_pago ENUM('CUOTA_INICIAL','CUOTA','VENTA_CONTADO','OTRO','AMORTIZACION')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE pagos MODIFY COLUMN concepto_pago ENUM('CUOTA_INICIAL','CUOTA','VENTA_CONTADO','OTRO')");
    }
};
