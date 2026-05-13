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
        Schema::table('mi_empresa', function (Blueprint $table) {
            $table->string('logo_login')->nullable()->after('logo');
            $table->string('logo_sidebar_compact')->nullable()->after('logo_login');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mi_empresa', function (Blueprint $table) {
            $table->dropColumn(['logo_login', 'logo_sidebar_compact']);
        });
    }
};
