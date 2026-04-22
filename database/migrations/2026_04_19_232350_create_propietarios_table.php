<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propietarios', function (Blueprint $table) {
            $table->id();
            $table->string('ci', 50)->unique();
            // NUEVO: Lugar de expedición del CI (Ej: SC, LP, CB)
            $table->string('lugar_expedicion', 20)->nullable(); 
            $table->string('nombre_completo');
            $table->string('telefono', 50)->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            // NUEVO: Agregamos el estado para poder Activar/Desactivar propietarios
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propietarios');
    }
};