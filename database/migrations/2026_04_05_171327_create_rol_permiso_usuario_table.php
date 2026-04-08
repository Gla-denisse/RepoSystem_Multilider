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
        Schema::create('rol_permiso_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('rol_permiso_id')->constrained('rol_permiso')->onDelete('cascade');
            $table->timestamps();

            // Evitar duplicados
            $table->unique(['user_id', 'rol_permiso_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rol_permiso_usuario');
    }
};
