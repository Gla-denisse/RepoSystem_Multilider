<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes_pagos', function (Blueprint $table) {
            $table->decimal('tasa_mora', 5, 2)->default(0)->after('tasa_interes');
        });
    }

    public function down(): void
    {
        Schema::table('planes_pagos', function (Blueprint $table) {
            $table->dropColumn('tasa_mora');
        });
    }
};
