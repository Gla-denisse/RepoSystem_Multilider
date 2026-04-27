<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('asesores', function (Blueprint $table) {
            $table->id();
            
            // RELACIÓN 1:1 CON USUARIOS
            // Un asesor DEBE tener un usuario para ingresar al sistema
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            
            // DATOS DEL ASESOR (Según tu diagrama)
            $table->string('nombre_completo');
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            
            // ESTADO PARA ACTIVAR/DESACTIVAR
            $table->boolean('estado')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asesores');
    }
};
