<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_empresa', function (Blueprint $table) {
            $table->string('imagen_hero')->nullable()->after('logo');
            $table->string('imagen_nosotros_1')->nullable()->after('descripcion_nosotros');
            $table->string('imagen_nosotros_2')->nullable()->after('imagen_nosotros_1');
        });
    }

    public function down(): void
    {
        Schema::table('mi_empresa', function (Blueprint $table) {
            $table->dropColumn(['imagen_hero', 'imagen_nosotros_1', 'imagen_nosotros_2']);
        });
    }
};