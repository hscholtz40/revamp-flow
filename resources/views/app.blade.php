<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style (no nonce: style-src uses unsafe-inline; nonce would require listing it and disables unsafe-inline in browsers) --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }

            :root {
                @php
                    $company = auth()->check() ? auth()->user()->currentCompany : null;
                @endphp

                --theme-primary-hue: {{ $company && $company->theme_primary_hue ? $company->theme_primary_hue : config('theme.primary.hue') }};
                --theme-primary-saturation: {{ $company && $company->theme_primary_saturation ? $company->theme_primary_saturation : config('theme.primary.saturation') }}%;
                --theme-primary-lightness: {{ $company && $company->theme_primary_lightness ? $company->theme_primary_lightness : config('theme.primary.lightness') }}%;
                --theme-primary-dark-mode-lightness: {{ $company && $company->theme_primary_dark_mode_lightness ? $company->theme_primary_dark_mode_lightness : config('theme.primary.dark_mode_lightness') }}%;

                --theme-secondary-hue: {{ $company && $company->theme_secondary_hue ? $company->theme_secondary_hue : config('theme.secondary.hue') }};
                --theme-secondary-saturation: {{ $company && $company->theme_secondary_saturation ? $company->theme_secondary_saturation : config('theme.secondary.saturation') }}%;
                --theme-secondary-lightness: {{ $company && $company->theme_secondary_lightness ? $company->theme_secondary_lightness : config('theme.secondary.lightness') }}%;
                --theme-secondary-dark-mode-lightness: {{ $company && $company->theme_secondary_dark_mode_lightness ? $company->theme_secondary_dark_mode_lightness : config('theme.secondary.dark_mode_lightness') }}%;

                --theme-accent-hue: {{ $company && $company->theme_accent_hue ? $company->theme_accent_hue : config('theme.accent.hue') }};
                --theme-accent-saturation: {{ $company && $company->theme_accent_saturation ? $company->theme_accent_saturation : config('theme.accent.saturation') }}%;
                --theme-accent-lightness: {{ $company && $company->theme_accent_lightness ? $company->theme_accent_lightness : config('theme.accent.lightness') }}%;
                --theme-accent-dark-mode-lightness: {{ $company && $company->theme_accent_dark_mode_lightness ? $company->theme_accent_dark_mode_lightness : config('theme.accent.dark_mode_lightness') }}%;

                --theme-chart-1-hue: {{ $company && $company->theme_chart_1_hue ? $company->theme_chart_1_hue : config('theme.chart.1.hue') }};
                --theme-chart-1-saturation: {{ $company && $company->theme_chart_1_saturation ? $company->theme_chart_1_saturation : config('theme.chart.1.saturation') }}%;
                --theme-chart-1-lightness: {{ $company && $company->theme_chart_1_lightness ? $company->theme_chart_1_lightness : config('theme.chart.1.lightness') }}%;

                --theme-chart-2-hue: {{ $company && $company->theme_chart_2_hue ? $company->theme_chart_2_hue : config('theme.chart.2.hue') }};
                --theme-chart-2-saturation: {{ $company && $company->theme_chart_2_saturation ? $company->theme_chart_2_saturation : config('theme.chart.2.saturation') }}%;
                --theme-chart-2-lightness: {{ $company && $company->theme_chart_2_lightness ? $company->theme_chart_2_lightness : config('theme.chart.2.lightness') }}%;

                --theme-chart-3-hue: {{ $company && $company->theme_chart_3_hue ? $company->theme_chart_3_hue : config('theme.chart.3.hue') }};
                --theme-chart-3-saturation: {{ $company && $company->theme_chart_3_saturation ? $company->theme_chart_3_saturation : config('theme.chart.3.saturation') }}%;
                --theme-chart-3-lightness: {{ $company && $company->theme_chart_3_lightness ? $company->theme_chart_3_lightness : config('theme.chart.3.lightness') }}%;

                --theme-chart-4-hue: {{ $company && $company->theme_chart_4_hue ? $company->theme_chart_4_hue : config('theme.chart.4.hue') }};
                --theme-chart-4-saturation: {{ $company && $company->theme_chart_4_saturation ? $company->theme_chart_4_saturation : config('theme.chart.4.saturation') }}%;
                --theme-chart-4-lightness: {{ $company && $company->theme_chart_4_lightness ? $company->theme_chart_4_lightness : config('theme.chart.4.lightness') }}%;

                --theme-chart-5-hue: {{ $company && $company->theme_chart_5_hue ? $company->theme_chart_5_hue : config('theme.chart.5.hue') }};
                --theme-chart-5-saturation: {{ $company && $company->theme_chart_5_saturation ? $company->theme_chart_5_saturation : config('theme.chart.5.saturation') }}%;
                --theme-chart-5-lightness: {{ $company && $company->theme_chart_5_lightness ? $company->theme_chart_5_lightness : config('theme.chart.5.lightness') }}%;

                --theme-sidebar-primary-hue: {{ $company && $company->theme_sidebar_primary_hue ? $company->theme_sidebar_primary_hue : config('theme.sidebar.primary.hue') }};
                --theme-sidebar-primary-saturation: {{ $company && $company->theme_sidebar_primary_saturation ? $company->theme_sidebar_primary_saturation : config('theme.sidebar.primary.saturation') }}%;
                --theme-sidebar-primary-lightness: {{ $company && $company->theme_sidebar_primary_lightness ? $company->theme_sidebar_primary_lightness : config('theme.sidebar.primary.lightness') }}%;
                --theme-sidebar-primary-dark-mode-lightness: {{ $company && $company->theme_sidebar_primary_dark_mode_lightness ? $company->theme_sidebar_primary_dark_mode_lightness : config('theme.sidebar.primary.dark_mode_lightness') }}%;

                --theme-sidebar-accent-hue: {{ $company && $company->theme_sidebar_accent_hue ? $company->theme_sidebar_accent_hue : config('theme.sidebar.accent.hue') }};
                --theme-sidebar-accent-saturation: {{ $company && $company->theme_sidebar_accent_saturation ? $company->theme_sidebar_accent_saturation : config('theme.sidebar.accent.saturation') }}%;
                --theme-sidebar-accent-lightness: {{ $company && $company->theme_sidebar_accent_lightness ? $company->theme_sidebar_accent_lightness : config('theme.sidebar.accent.lightness') }}%;

                --theme-background-light-mode-lightness: {{ $company && $company->theme_background_light_mode_lightness ? $company->theme_background_light_mode_lightness : config('theme.background.light_mode_lightness') }}%;
                --theme-background-dark-mode-lightness: {{ $company && $company->theme_background_dark_mode_lightness ? $company->theme_background_dark_mode_lightness : config('theme.background.dark_mode_lightness') }}%;

                --theme-layout-sidebar-width: {{ $company && $company->theme_layout_sidebar_width ? $company->theme_layout_sidebar_width : config('theme.layout.sidebar_width') }};
                --theme-layout-sidebar-collapsed-width: {{ $company && $company->theme_layout_sidebar_collapsed_width ? $company->theme_layout_sidebar_collapsed_width : config('theme.layout.sidebar_collapsed_width') }};
                --theme-layout-header-height: {{ $company && $company->theme_layout_header_height ? $company->theme_layout_header_height : config('theme.layout.header_height') }};
                --theme-layout-border-radius: {{ $company && $company->theme_layout_border_radius ? $company->theme_layout_border_radius : config('theme.layout.border_radius') }};
                --theme-layout-spacing-unit: {{ $company && $company->theme_layout_spacing_unit ? $company->theme_layout_spacing_unit : config('theme.layout.spacing_unit') }};
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
