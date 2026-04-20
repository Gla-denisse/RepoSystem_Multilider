<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manzanos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique(); // Ej: MZ-01, MZ-02
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manzanos');
    }
};