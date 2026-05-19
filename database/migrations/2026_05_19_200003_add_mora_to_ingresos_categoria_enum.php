<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ingresos MODIFY COLUMN categoria ENUM('VENTA_CONTADO','CUOTA_INICIAL','CUOTA','OTRO','MORA')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ingresos MODIFY COLUMN categoria ENUM('VENTA_CONTADO','CUOTA_INICIAL','CUOTA','OTRO')");
    }
};
