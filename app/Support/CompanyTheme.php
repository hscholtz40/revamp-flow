<?php

namespace App\Support;

use App\Models\Company;

class CompanyTheme
{
    /**
     * Map of company theme columns to config/theme.php paths.
     *
     * @return array<string, string>
     */
    public static function configPaths(): array
    {
        return [
            'theme_primary_hue' => 'theme.primary.hue',
            'theme_primary_saturation' => 'theme.primary.saturation',
            'theme_primary_lightness' => 'theme.primary.lightness',
            'theme_primary_dark_mode_lightness' => 'theme.primary.dark_mode_lightness',
            'theme_secondary_hue' => 'theme.secondary.hue',
            'theme_secondary_saturation' => 'theme.secondary.saturation',
            'theme_secondary_lightness' => 'theme.secondary.lightness',
            'theme_secondary_dark_mode_lightness' => 'theme.secondary.dark_mode_lightness',
            'theme_accent_hue' => 'theme.accent.hue',
            'theme_accent_saturation' => 'theme.accent.saturation',
            'theme_accent_lightness' => 'theme.accent.lightness',
            'theme_accent_dark_mode_lightness' => 'theme.accent.dark_mode_lightness',
            'theme_chart_1_hue' => 'theme.chart.1.hue',
            'theme_chart_1_saturation' => 'theme.chart.1.saturation',
            'theme_chart_1_lightness' => 'theme.chart.1.lightness',
            'theme_chart_2_hue' => 'theme.chart.2.hue',
            'theme_chart_2_saturation' => 'theme.chart.2.saturation',
            'theme_chart_2_lightness' => 'theme.chart.2.lightness',
            'theme_chart_3_hue' => 'theme.chart.3.hue',
            'theme_chart_3_saturation' => 'theme.chart.3.saturation',
            'theme_chart_3_lightness' => 'theme.chart.3.lightness',
            'theme_chart_4_hue' => 'theme.chart.4.hue',
            'theme_chart_4_saturation' => 'theme.chart.4.saturation',
            'theme_chart_4_lightness' => 'theme.chart.4.lightness',
            'theme_chart_5_hue' => 'theme.chart.5.hue',
            'theme_chart_5_saturation' => 'theme.chart.5.saturation',
            'theme_chart_5_lightness' => 'theme.chart.5.lightness',
            'theme_sidebar_primary_hue' => 'theme.sidebar.primary.hue',
            'theme_sidebar_primary_saturation' => 'theme.sidebar.primary.saturation',
            'theme_sidebar_primary_lightness' => 'theme.sidebar.primary.lightness',
            'theme_sidebar_primary_dark_mode_lightness' => 'theme.sidebar.primary.dark_mode_lightness',
            'theme_sidebar_accent_hue' => 'theme.sidebar.accent.hue',
            'theme_sidebar_accent_saturation' => 'theme.sidebar.accent.saturation',
            'theme_sidebar_accent_lightness' => 'theme.sidebar.accent.lightness',
            'theme_background_light_mode_lightness' => 'theme.background.light_mode_lightness',
            'theme_background_dark_mode_lightness' => 'theme.background.dark_mode_lightness',
            'theme_layout_sidebar_width' => 'theme.layout.sidebar_width',
            'theme_layout_sidebar_collapsed_width' => 'theme.layout.sidebar_collapsed_width',
            'theme_layout_header_height' => 'theme.layout.header_height',
            'theme_layout_border_radius' => 'theme.layout.border_radius',
            'theme_layout_spacing_unit' => 'theme.layout.spacing_unit',
        ];
    }

    /**
     * Resolve theme values for a company, falling back to config defaults.
     *
     * @return array<string, int|string|float>
     */
    public static function resolved(?Company $company): array
    {
        $theme = [];

        foreach (self::configPaths() as $property => $configPath) {
            $value = $company?->{$property};
            $theme[$property] = ($value !== null && $value !== '')
                ? $value
                : config($configPath);
        }

        return $theme;
    }
}
