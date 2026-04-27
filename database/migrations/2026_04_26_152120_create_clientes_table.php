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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            
            // Relación con la tabla users nativa de Laravel
            $table->foreignId('user_id')->unique()->constrained('users')->onDelete('cascade');
            
            // Datos específicos del cliente
            $table->string('ci')->unique();
            $table->string('lugar_expedicion', 10)->nullable(); // Ej: SC, LP, CB
            $table->string('nombre_completo');
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('direccion')->nullable();
            
            // Estado para el soft-delete (activar/desactivar)
            $table->boolean('estado')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
