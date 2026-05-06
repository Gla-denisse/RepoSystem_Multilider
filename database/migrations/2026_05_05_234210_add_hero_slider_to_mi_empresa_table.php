<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mi_empresa', function (Blueprint $table) {
            // Slider Image 1
            $table->string('hero_image_1')->nullable()->after('logo');
            $table->string('hero_title_1')->nullable()->after('hero_image_1');
            $table->string('hero_subtitle_1')->nullable()->after('hero_title_1');

            // Slider Image 2
            $table->string('hero_image_2')->nullable()->after('hero_subtitle_1');
            $table->string('hero_title_2')->nullable()->after('hero_image_2');
            $table->string('hero_subtitle_2')->nullable()->after('hero_title_2');

            // Slider Image 3
            $table->string('hero_image_3')->nullable()->after('hero_subtitle_2');
            $table->string('hero_title_3')->nullable()->after('hero_image_3');
            $table->string('hero_subtitle_3')->nullable()->after('hero_title_3');
        });
    }

    public function down(): void
    {
        Schema::table('mi_empresa', function (Blueprint $table) {
            $table->dropColumn([
                'hero_image_1', 'hero_title_1', 'hero_subtitle_1',
                'hero_image_2', 'hero_title_2', 'hero_subtitle_2',
                'hero_image_3', 'hero_title_3', 'hero_subtitle_3'
            ]);
        });
    }
};