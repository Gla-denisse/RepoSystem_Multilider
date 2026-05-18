<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            // CI pasa a nullable: las empresas pueden no tener CI
            $table->string('ci', 50)->nullable()->change();

            $table->enum('tipo', ['persona_natural', 'empresa'])
                  ->default('persona_natural')
                  ->after('id');

            // Campos exclusivos para tipo = empresa
            $table->string('nombre_empresa')->nullable()->after('tipo');
            $table->string('nit', 50)->nullable()->after('nombre_empresa');
        });
    }

    public function down(): void
    {
        Schema::table('propietarios', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'nombre_empresa', 'nit']);
            $table->string('ci', 50)->nullable(false)->change();
        });
    }
};
