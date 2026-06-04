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
        Schema::table('users', function (Blueprint $table) {
            // Theme colors (HSL values)
            $table->integer('theme_primary_hue')->nullable()->default(215);
            $table->integer('theme_primary_saturation')->nullable()->default(59);
            $table->integer('theme_primary_lightness')->nullable()->default(41);
            $table->integer('theme_primary_dark_mode_lightness')->nullable()->default(55);

            $table->integer('theme_secondary_hue')->nullable()->default(178);
            $table->integer('theme_secondary_saturation')->nullable()->default(62);
            $table->integer('theme_secondary_lightness')->nullable()->default(46);
            $table->integer('theme_secondary_dark_mode_lightness')->nullable()->default(55);

            $table->integer('theme_accent_hue')->nullable()->default(215);
            $table->integer('theme_accent_saturation')->nullable()->default(48);
            $table->integer('theme_accent_lightness')->nullable()->default(48);
            $table->integer('theme_accent_dark_mode_lightness')->nullable()->default(60);

            // Chart colors
            $table->integer('theme_chart_1_hue')->nullable()->default(215);
            $table->integer('theme_chart_1_saturation')->nullable()->default(59);
            $table->integer('theme_chart_1_lightness')->nullable()->default(41);

            $table->integer('theme_chart_2_hue')->nullable()->default(178);
            $table->integer('theme_chart_2_saturation')->nullable()->default(62);
            $table->integer('theme_chart_2_lightness')->nullable()->default(46);

            $table->integer('theme_chart_3_hue')->nullable()->default(215);
            $table->integer('theme_chart_3_saturation')->nullable()->default(48);
            $table->integer('theme_chart_3_lightness')->nullable()->default(48);

            $table->integer('theme_chart_4_hue')->nullable()->default(178);
            $table->integer('theme_chart_4_saturation')->nullable()->default(50);
            $table->integer('theme_chart_4_lightness')->nullable()->default(55);

            $table->integer('theme_chart_5_hue')->nullable()->default(215);
            $table->integer('theme_chart_5_saturation')->nullable()->default(55);
            $table->integer('theme_chart_5_lightness')->nullable()->default(55);

            // Sidebar colors
            $table->integer('theme_sidebar_primary_hue')->nullable()->default(215);
            $table->integer('theme_sidebar_primary_saturation')->nullable()->default(59);
            $table->integer('theme_sidebar_primary_lightness')->nullable()->default(41);
            $table->integer('theme_sidebar_primary_dark_mode_lightness')->nullable()->default(55);

            $table->integer('theme_sidebar_accent_hue')->nullable()->default(215);
            $table->integer('theme_sidebar_accent_saturation')->nullable()->default(59);
            $table->integer('theme_sidebar_accent_lightness')->nullable()->default(95);

            // Background colors
            $table->integer('theme_background_light_mode_lightness')->nullable()->default(100);
            $table->integer('theme_background_dark_mode_lightness')->nullable()->default(3);

            // Layout variables
            $table->string('theme_layout_sidebar_width')->nullable()->default('260px');
            $table->string('theme_layout_sidebar_collapsed_width')->nullable()->default('64px');
            $table->string('theme_layout_header_height')->nullable()->default('64px');
            $table->string('theme_layout_border_radius')->nullable()->default('0.5rem');
            $table->string('theme_layout_spacing_unit')->nullable()->default('1rem');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'theme_primary_hue',
                'theme_primary_saturation',
                'theme_primary_lightness',
                'theme_primary_dark_mode_lightness',
                'theme_secondary_hue',
                'theme_secondary_saturation',
                'theme_secondary_lightness',
                'theme_secondary_dark_mode_lightness',
                'theme_accent_hue',
                'theme_accent_saturation',
                'theme_accent_lightness',
                'theme_accent_dark_mode_lightness',
                'theme_chart_1_hue',
                'theme_chart_1_saturation',
                'theme_chart_1_lightness',
                'theme_chart_2_hue',
                'theme_chart_2_saturation',
                'theme_chart_2_lightness',
                'theme_chart_3_hue',
                'theme_chart_3_saturation',
                'theme_chart_3_lightness',
                'theme_chart_4_hue',
                'theme_chart_4_saturation',
                'theme_chart_4_lightness',
                'theme_chart_5_hue',
                'theme_chart_5_saturation',
                'theme_chart_5_lightness',
                'theme_sidebar_primary_hue',
                'theme_sidebar_primary_saturation',
                'theme_sidebar_primary_lightness',
                'theme_sidebar_primary_dark_mode_lightness',
                'theme_sidebar_accent_hue',
                'theme_sidebar_accent_saturation',
                'theme_sidebar_accent_lightness',
                'theme_background_light_mode_lightness',
                'theme_background_dark_mode_lightness',
                'theme_layout_sidebar_width',
                'theme_layout_sidebar_collapsed_width',
                'theme_layout_header_height',
                'theme_layout_border_radius',
                'theme_layout_spacing_unit',
            ]);
        });
    }
};
