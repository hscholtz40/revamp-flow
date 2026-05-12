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
                --theme-primary-hue: {{ config('theme.primary.hue') }};
                --theme-primary-saturation: {{ config('theme.primary.saturation') }}%;
                --theme-primary-lightness: {{ config('theme.primary.lightness') }}%;
                --theme-primary-dark-mode-lightness: {{ config('theme.primary.dark_mode_lightness') }}%;

                --theme-secondary-hue: {{ config('theme.secondary.hue') }};
                --theme-secondary-saturation: {{ config('theme.secondary.saturation') }}%;
                --theme-secondary-lightness: {{ config('theme.secondary.lightness') }}%;
                --theme-secondary-dark-mode-lightness: {{ config('theme.secondary.dark_mode_lightness') }}%;

                --theme-accent-hue: {{ config('theme.accent.hue') }};
                --theme-accent-saturation: {{ config('theme.accent.saturation') }}%;
                --theme-accent-lightness: {{ config('theme.accent.lightness') }}%;
                --theme-accent-dark-mode-lightness: {{ config('theme.accent.dark_mode_lightness') }}%;

                --theme-chart-1-hue: {{ config('theme.chart.1.hue') }};
                --theme-chart-1-saturation: {{ config('theme.chart.1.saturation') }}%;
                --theme-chart-1-lightness: {{ config('theme.chart.1.lightness') }}%;

                --theme-chart-2-hue: {{ config('theme.chart.2.hue') }};
                --theme-chart-2-saturation: {{ config('theme.chart.2.saturation') }}%;
                --theme-chart-2-lightness: {{ config('theme.chart.2.lightness') }}%;

                --theme-chart-3-hue: {{ config('theme.chart.3.hue') }};
                --theme-chart-3-saturation: {{ config('theme.chart.3.saturation') }}%;
                --theme-chart-3-lightness: {{ config('theme.chart.3.lightness') }}%;

                --theme-chart-4-hue: {{ config('theme.chart.4.hue') }};
                --theme-chart-4-saturation: {{ config('theme.chart.4.saturation') }}%;
                --theme-chart-4-lightness: {{ config('theme.chart.4.lightness') }}%;

                --theme-chart-5-hue: {{ config('theme.chart.5.hue') }};
                --theme-chart-5-saturation: {{ config('theme.chart.5.saturation') }}%;
                --theme-chart-5-lightness: {{ config('theme.chart.5.lightness') }}%;

                --theme-sidebar-primary-hue: {{ config('theme.sidebar.primary.hue') }};
                --theme-sidebar-primary-saturation: {{ config('theme.sidebar.primary.saturation') }}%;
                --theme-sidebar-primary-lightness: {{ config('theme.sidebar.primary.lightness') }}%;
                --theme-sidebar-primary-dark-mode-lightness: {{ config('theme.sidebar.primary.dark_mode_lightness') }}%;

                --theme-sidebar-accent-hue: {{ config('theme.sidebar.accent.hue') }};
                --theme-sidebar-accent-saturation: {{ config('theme.sidebar.accent.saturation') }}%;
                --theme-sidebar-accent-lightness: {{ config('theme.sidebar.accent.lightness') }}%;

                --theme-background-light-mode-lightness: {{ config('theme.background.light_mode_lightness') }}%;
                --theme-background-dark-mode-lightness: {{ config('theme.background.dark_mode_lightness') }}%;

                --theme-layout-sidebar-width: {{ config('theme.layout.sidebar_width') }};
                --theme-layout-sidebar-collapsed-width: {{ config('theme.layout.sidebar_collapsed_width') }};
                --theme-layout-header-height: {{ config('theme.layout.header_height') }};
                --theme-layout-border-radius: {{ config('theme.layout.border_radius') }};
                --theme-layout-spacing-unit: {{ config('theme.layout.spacing_unit') }};
            }
        </style>

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico?v={{ config('app.version', '1.0.0') }}" sizes="any">
        <link rel="icon" href="/favicon.svg?v={{ config('app.version', '1.0.0') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v={{ config('app.version', '1.0.0') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
