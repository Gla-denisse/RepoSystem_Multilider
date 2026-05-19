<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->foreignId('reprogramacion_id')
                  ->nullable()
                  ->after('estado')
                  ->constrained('reprogramaciones')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cuotas', function (Blueprint $table) {
            $table->dropForeign(['reprogramacion_id']);
            $table->dropColumn('reprogramacion_id');
        });
    }
};
