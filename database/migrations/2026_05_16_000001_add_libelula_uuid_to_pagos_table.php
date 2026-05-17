<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            // UUID propio de Libélula (su id_transaccion), necesario para el endpoint /rest/deuda/consultar
            $table->string('libelula_uuid', 36)->nullable()->index()->after('id_transaccion_libelula');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['libelula_uuid']);
            $table->dropColumn('libelula_uuid');
        });
    }
};
