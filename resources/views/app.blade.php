<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
                
                // Set theme variables immediately via JavaScript
                @php
                    $company = auth()->check() ? auth()->user()->getCurrentCompany() : null;

                    $getThemeValue = function ($property, $configPath) use ($company) {
                        if ($company && $company->{$property} !== null) {
                            return $company->{$property};
                        }

                        return config($configPath);
                    };
                @endphp
                
                const root = document.documentElement;
                
                root.style.setProperty('--theme-primary-hue', '{{ $getThemeValue('theme_primary_hue', 'theme.primary.hue') }}');
                root.style.setProperty('--theme-primary-saturation', '{{ $getThemeValue('theme_primary_saturation', 'theme.primary.saturation') }}%');
                root.style.setProperty('--theme-primary-lightness', '{{ $getThemeValue('theme_primary_lightness', 'theme.primary.lightness') }}%');
                root.style.setProperty('--theme-primary-dark-mode-lightness', '{{ $getThemeValue('theme_primary_dark_mode_lightness', 'theme.primary.dark_mode_lightness') }}');
                
                root.style.setProperty('--theme-secondary-hue', '{{ $getThemeValue('theme_secondary_hue', 'theme.secondary.hue') }}');
                root.style.setProperty('--theme-secondary-saturation', '{{ $getThemeValue('theme_secondary_saturation', 'theme.secondary.saturation') }}%');
                root.style.setProperty('--theme-secondary-lightness', '{{ $getThemeValue('theme_secondary_lightness', 'theme.secondary.lightness') }}%');
                root.style.setProperty('--theme-secondary-dark-mode-lightness', '{{ $getThemeValue('theme_secondary_dark_mode_lightness', 'theme.secondary.dark_mode_lightness') }}');
                
                root.style.setProperty('--theme-accent-hue', '{{ $getThemeValue('theme_accent_hue', 'theme.accent.hue') }}');
                root.style.setProperty('--theme-accent-saturation', '{{ $getThemeValue('theme_accent_saturation', 'theme.accent.saturation') }}%');
                root.style.setProperty('--theme-accent-lightness', '{{ $getThemeValue('theme_accent_lightness', 'theme.accent.lightness') }}%');
                root.style.setProperty('--theme-accent-dark-mode-lightness', '{{ $getThemeValue('theme_accent_dark_mode_lightness', 'theme.accent.dark_mode_lightness') }}');
                
                root.style.setProperty('--theme-chart-1-hue', '{{ $getThemeValue('theme_chart_1_hue', 'theme.chart.1.hue') }}');
                root.style.setProperty('--theme-chart-1-saturation', '{{ $getThemeValue('theme_chart_1_saturation', 'theme.chart.1.saturation') }}%');
                root.style.setProperty('--theme-chart-1-lightness', '{{ $getThemeValue('theme_chart_1_lightness', 'theme.chart.1.lightness') }}');
                
                root.style.setProperty('--theme-chart-2-hue', '{{ $getThemeValue('theme_chart_2_hue', 'theme.chart.2.hue') }}');
                root.style.setProperty('--theme-chart-2-saturation', '{{ $getThemeValue('theme_chart_2_saturation', 'theme.chart.2.saturation') }}%');
                root.style.setProperty('--theme-chart-2-lightness', '{{ $getThemeValue('theme_chart_2_lightness', 'theme.chart.2.lightness') }}');
                
                root.style.setProperty('--theme-chart-3-hue', '{{ $getThemeValue('theme_chart_3_hue', 'theme.chart.3.hue') }}');
                root.style.setProperty('--theme-chart-3-saturation', '{{ $getThemeValue('theme_chart_3_saturation', 'theme.chart.3.saturation') }}%');
                root.style.setProperty('--theme-chart-3-lightness', '{{ $getThemeValue('theme_chart_3_lightness', 'theme.chart.3.lightness') }}');
                
                root.style.setProperty('--theme-chart-4-hue', '{{ $getThemeValue('theme_chart_4_hue', 'theme.chart.4.hue') }}');
                root.style.setProperty('--theme-chart-4-saturation', '{{ $getThemeValue('theme_chart_4_saturation', 'theme.chart.4.saturation') }}%');
                root.style.setProperty('--theme-chart-4-lightness', '{{ $getThemeValue('theme_chart_4_lightness', 'theme.chart.4.lightness') }}');
                
                root.style.setProperty('--theme-chart-5-hue', '{{ $getThemeValue('theme_chart_5_hue', 'theme.chart.5.hue') }}');
                root.style.setProperty('--theme-chart-5-saturation', '{{ $getThemeValue('theme_chart_5_saturation', 'theme.chart.5.saturation') }}%');
                root.style.setProperty('--theme-chart-5-lightness', '{{ $getThemeValue('theme_chart_5_lightness', 'theme.chart.5.lightness') }}');
                
                root.style.setProperty('--theme-sidebar-primary-hue', '{{ $getThemeValue('theme_sidebar_primary_hue', 'theme.sidebar.primary.hue') }}');
                root.style.setProperty('--theme-sidebar-primary-saturation', '{{ $getThemeValue('theme_sidebar_primary_saturation', 'theme.sidebar.primary.saturation') }}%');
                root.style.setProperty('--theme-sidebar-primary-lightness', '{{ $getThemeValue('theme_sidebar_primary_lightness', 'theme.sidebar.primary.lightness') }}');
                root.style.setProperty('--theme-sidebar-primary-dark-mode-lightness', '{{ $getThemeValue('theme_sidebar_primary_dark_mode_lightness', 'theme.sidebar.primary.dark_mode_lightness') }}');
                
                root.style.setProperty('--theme-sidebar-accent-hue', '{{ $getThemeValue('theme_sidebar_accent_hue', 'theme.sidebar.accent.hue') }}');
                root.style.setProperty('--theme-sidebar-accent-saturation', '{{ $getThemeValue('theme_sidebar_accent_saturation', 'theme.sidebar.accent.saturation') }}%');
                root.style.setProperty('--theme-sidebar-accent-lightness', '{{ $getThemeValue('theme_sidebar_accent_lightness', 'theme.sidebar.accent.lightness') }}');
                
                root.style.setProperty('--theme-background-light-mode-lightness', '{{ $getThemeValue('theme_background_light_mode_lightness', 'theme.background.light_mode_lightness') }}%');
                root.style.setProperty('--theme-background-dark-mode-lightness', '{{ $getThemeValue('theme_background_dark_mode_lightness', 'theme.background.dark_mode_lightness') }}%');
                
                root.style.setProperty('--theme-layout-sidebar-width', '{{ $getThemeValue('theme_layout_sidebar_width', 'theme.layout.sidebar_width') }}');
                root.style.setProperty('--theme-layout-sidebar-collapsed-width', '{{ $getThemeValue('theme_layout_sidebar_collapsed_width', 'theme.layout.sidebar_collapsed_width') }}');
                root.style.setProperty('--theme-layout-header-height', '{{ $getThemeValue('theme_layout_header_height', 'theme.layout.header_height') }}');
                root.style.setProperty('--theme-layout-border-radius', '{{ $getThemeValue('theme_layout_border_radius', 'theme.layout.border_radius') }}');
                root.style.setProperty('--theme-layout-spacing-unit', '{{ $getThemeValue('theme_layout_spacing_unit', 'theme.layout.spacing_unit') }}');
            })();
        </script>

        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        @if(auth()->check() && auth()->user()->currentCompany && auth()->user()->currentCompany->favicon)
            <link rel="icon" href="{{ auth()->user()->currentCompany->favicon }}?v={{ config('app.version', '1.0.0') }}" sizes="any">
            <link rel="apple-touch-icon" href="{{ auth()->user()->currentCompany->favicon }}?v={{ config('app.version', '1.0.0') }}">
        @else
            <link rel="icon" href="/favicon.ico?v={{ config('app.version', '1.0.0') }}" sizes="any">
            <link rel="icon" href="/favicon.svg?v={{ config('app.version', '1.0.0') }}" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ config('app.version', '1.0.0') }}">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>