<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Theme Colors
    |--------------------------------------------------------------------------
    |
    | These colors define the visual theme of the application. They can be
    | overridden via environment variables for white-labeling purposes.
    |
    | Format: HSL values (hue saturation lightness)
    | - Hue: 0-360 (degrees on color wheel)
    | - Saturation: 0-100%
    | - Lightness: 0-100%
    |
    */

    // Primary brand color (main action buttons, links, highlights)
    'primary' => [
        'hue' => env('THEME_PRIMARY_HUE', 215),
        'saturation' => env('THEME_PRIMARY_SATURATION', 59),
        'lightness' => env('THEME_PRIMARY_LIGHTNESS', 41),
        'dark_mode_lightness' => env('THEME_PRIMARY_DARK_MODE_LIGHTNESS', 55),
    ],

    // Secondary brand color (complementary actions, accents)
    'secondary' => [
        'hue' => env('THEME_SECONDARY_HUE', 178),
        'saturation' => env('THEME_SECONDARY_SATURATION', 62),
        'lightness' => env('THEME_SECONDARY_LIGHTNESS', 46),
        'dark_mode_lightness' => env('THEME_SECONDARY_DARK_MODE_LIGHTNESS', 55),
    ],

    // Accent color (hover states, subtle highlights)
    'accent' => [
        'hue' => env('THEME_ACCENT_HUE', 215),
        'saturation' => env('THEME_ACCENT_SATURATION', 48),
        'lightness' => env('THEME_ACCENT_LIGHTNESS', 48),
        'dark_mode_lightness' => env('THEME_ACCENT_DARK_MODE_LIGHTNESS', 60),
    ],

    // Chart colors (for data visualization)
    'chart' => [
        1 => [
            'hue' => env('THEME_CHART_1_HUE', 215),
            'saturation' => env('THEME_CHART_1_SATURATION', 59),
            'lightness' => env('THEME_CHART_1_LIGHTNESS', 41),
        ],
        2 => [
            'hue' => env('THEME_CHART_2_HUE', 178),
            'saturation' => env('THEME_CHART_2_SATURATION', 62),
            'lightness' => env('THEME_CHART_2_LIGHTNESS', 46),
        ],
        3 => [
            'hue' => env('THEME_CHART_3_HUE', 215),
            'saturation' => env('THEME_CHART_3_SATURATION', 48),
            'lightness' => env('THEME_CHART_3_LIGHTNESS', 48),
        ],
        4 => [
            'hue' => env('THEME_CHART_4_HUE', 178),
            'saturation' => env('THEME_CHART_4_SATURATION', 50),
            'lightness' => env('THEME_CHART_4_LIGHTNESS', 55),
        ],
        5 => [
            'hue' => env('THEME_CHART_5_HUE', 215),
            'saturation' => env('THEME_CHART_5_SATURATION', 55),
            'lightness' => env('THEME_CHART_5_LIGHTNESS', 55),
        ],
    ],

    // Sidebar colors
    'sidebar' => [
        'primary' => [
            'hue' => env('THEME_SIDEBAR_PRIMARY_HUE', 215),
            'saturation' => env('THEME_SIDEBAR_PRIMARY_SATURATION', 59),
            'lightness' => env('THEME_SIDEBAR_PRIMARY_LIGHTNESS', 41),
            'dark_mode_lightness' => env('THEME_SIDEBAR_PRIMARY_DARK_MODE_LIGHTNESS', 55),
        ],
        'accent' => [
            'hue' => env('THEME_SIDEBAR_ACCENT_HUE', 215),
            'saturation' => env('THEME_SIDEBAR_ACCENT_SATURATION', 59),
            'lightness' => env('THEME_SIDEBAR_ACCENT_LIGHTNESS', 95),
        ],
    ],

    // Background colors (for black/dark themes)
    'background' => [
        'light_mode_lightness' => env('THEME_BACKGROUND_LIGHT_MODE_LIGHTNESS', 100),
        'dark_mode_lightness' => env('THEME_BACKGROUND_DARK_MODE_LIGHTNESS', 3),
    ],

    // Layout variables
    'layout' => [
        'sidebar_width' => env('THEME_LAYOUT_SIDEBAR_WIDTH', '260px'),
        'sidebar_collapsed_width' => env('THEME_LAYOUT_SIDEBAR_COLLAPSED_WIDTH', '64px'),
        'header_height' => env('THEME_LAYOUT_HEADER_HEIGHT', '64px'),
        'border_radius' => env('THEME_LAYOUT_BORDER_RADIUS', '0.5rem'),
        'spacing_unit' => env('THEME_LAYOUT_SPACING_UNIT', '1rem'),
    ],

    // Helper function to format HSL values as CSS string
    'hsl' => function ($hue, $saturation, $lightness) {
        return "hsl({$hue} {$saturation}% {$lightness}%)";
    },
];
