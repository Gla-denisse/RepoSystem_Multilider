<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mi_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('logo')->nullable();
            $table->string('eslogan')->nullable();
            $table->text('descripcion_nosotros')->nullable();
            $table->text('mision')->nullable();
            $table->text('vision')->nullable();
            $table->text('valores')->nullable();
            
            // Contacto
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            
            // Redes Sociales
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('youtube')->nullable();
            
            // Configuración Landing
            $table->text('mapa_iframe')->nullable(); // Para Google Maps
            $table->string('color_primario')->default('#000000');
            $table->string('color_secundario')->default('#ffffff');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mi_empresa');
    }
};