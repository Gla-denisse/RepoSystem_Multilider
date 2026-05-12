<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('ci_pagador')->nullable()->after('observaciones');
            $table->string('telefono_pagador')->nullable()->after('ci_pagador');
            $table->string('nombres_pagador')->nullable()->after('telefono_pagador');
            $table->string('apellidos_pagador')->nullable()->after('nombres_pagador');
            $table->string('correo_pagador')->nullable()->after('apellidos_pagador');
            $table->string('id_transaccion_libelula')->nullable()->after('correo_pagador')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn([
                'ci_pagador', 'telefono_pagador', 'nombres_pagador',
                'apellidos_pagador', 'correo_pagador', 'id_transaccion_libelula'
            ]);
        });
    }
};
