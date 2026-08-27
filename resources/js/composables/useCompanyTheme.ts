/**
 * Company brand theme (HSL CSS variables). Separate from light/dark appearance mode.
 */

export type CompanyThemeValues = {
    theme_primary_hue: number | string;
    theme_primary_saturation: number | string;
    theme_primary_lightness: number | string;
    theme_primary_dark_mode_lightness: number | string;
    theme_secondary_hue: number | string;
    theme_secondary_saturation: number | string;
    theme_secondary_lightness: number | string;
    theme_secondary_dark_mode_lightness: number | string;
    theme_accent_hue: number | string;
    theme_accent_saturation: number | string;
    theme_accent_lightness: number | string;
    theme_accent_dark_mode_lightness: number | string;
    theme_chart_1_hue: number | string;
    theme_chart_1_saturation: number | string;
    theme_chart_1_lightness: number | string;
    theme_chart_2_hue: number | string;
    theme_chart_2_saturation: number | string;
    theme_chart_2_lightness: number | string;
    theme_chart_3_hue: number | string;
    theme_chart_3_saturation: number | string;
    theme_chart_3_lightness: number | string;
    theme_chart_4_hue: number | string;
    theme_chart_4_saturation: number | string;
    theme_chart_4_lightness: number | string;
    theme_chart_5_hue: number | string;
    theme_chart_5_saturation: number | string;
    theme_chart_5_lightness: number | string;
    theme_sidebar_primary_hue: number | string;
    theme_sidebar_primary_saturation: number | string;
    theme_sidebar_primary_lightness: number | string;
    theme_sidebar_primary_dark_mode_lightness: number | string;
    theme_sidebar_accent_hue: number | string;
    theme_sidebar_accent_saturation: number | string;
    theme_sidebar_accent_lightness: number | string;
    theme_background_light_mode_lightness: number | string;
    theme_background_dark_mode_lightness: number | string;
    theme_layout_sidebar_width: string;
    theme_layout_sidebar_collapsed_width: string;
    theme_layout_header_height: string;
    theme_layout_border_radius: string;
    theme_layout_spacing_unit: string;
};

function asString(value: number | string | null | undefined, fallback: string | number): string {
    if (value === null || value === undefined || value === '') {
        return String(fallback);
    }

    return String(value);
}

/**
 * Apply company theme CSS variables on :root.
 * Suffix rules match resources/views/app.blade.php so Inertia navigations
 * (e.g. login) match a full page refresh.
 */
export function applyCompanyTheme(theme: Partial<CompanyThemeValues> | null | undefined): void {
    const root = document.documentElement;
    const t = theme ?? {};

    root.style.setProperty('--theme-primary-hue', asString(t.theme_primary_hue, 215));
    root.style.setProperty('--theme-primary-saturation', `${asString(t.theme_primary_saturation, 59)}%`);
    root.style.setProperty('--theme-primary-lightness', `${asString(t.theme_primary_lightness, 41)}%`);
    root.style.setProperty('--theme-primary-dark-mode-lightness', asString(t.theme_primary_dark_mode_lightness, 55));

    root.style.setProperty('--theme-secondary-hue', asString(t.theme_secondary_hue, 178));
    root.style.setProperty('--theme-secondary-saturation', `${asString(t.theme_secondary_saturation, 62)}%`);
    root.style.setProperty('--theme-secondary-lightness', `${asString(t.theme_secondary_lightness, 46)}%`);
    root.style.setProperty('--theme-secondary-dark-mode-lightness', asString(t.theme_secondary_dark_mode_lightness, 55));

    root.style.setProperty('--theme-accent-hue', asString(t.theme_accent_hue, 215));
    root.style.setProperty('--theme-accent-saturation', `${asString(t.theme_accent_saturation, 48)}%`);
    root.style.setProperty('--theme-accent-lightness', `${asString(t.theme_accent_lightness, 48)}%`);
    root.style.setProperty('--theme-accent-dark-mode-lightness', asString(t.theme_accent_dark_mode_lightness, 60));

    root.style.setProperty('--theme-chart-1-hue', asString(t.theme_chart_1_hue, 215));
    root.style.setProperty('--theme-chart-1-saturation', `${asString(t.theme_chart_1_saturation, 59)}%`);
    root.style.setProperty('--theme-chart-1-lightness', asString(t.theme_chart_1_lightness, 41));

    root.style.setProperty('--theme-chart-2-hue', asString(t.theme_chart_2_hue, 178));
    root.style.setProperty('--theme-chart-2-saturation', `${asString(t.theme_chart_2_saturation, 62)}%`);
    root.style.setProperty('--theme-chart-2-lightness', asString(t.theme_chart_2_lightness, 46));

    root.style.setProperty('--theme-chart-3-hue', asString(t.theme_chart_3_hue, 215));
    root.style.setProperty('--theme-chart-3-saturation', `${asString(t.theme_chart_3_saturation, 48)}%`);
    root.style.setProperty('--theme-chart-3-lightness', asString(t.theme_chart_3_lightness, 48));

    root.style.setProperty('--theme-chart-4-hue', asString(t.theme_chart_4_hue, 178));
    root.style.setProperty('--theme-chart-4-saturation', `${asString(t.theme_chart_4_saturation, 50)}%`);
    root.style.setProperty('--theme-chart-4-lightness', asString(t.theme_chart_4_lightness, 55));

    root.style.setProperty('--theme-chart-5-hue', asString(t.theme_chart_5_hue, 215));
    root.style.setProperty('--theme-chart-5-saturation', `${asString(t.theme_chart_5_saturation, 55)}%`);
    root.style.setProperty('--theme-chart-5-lightness', asString(t.theme_chart_5_lightness, 55));

    root.style.setProperty('--theme-sidebar-primary-hue', asString(t.theme_sidebar_primary_hue, 215));
    root.style.setProperty('--theme-sidebar-primary-saturation', `${asString(t.theme_sidebar_primary_saturation, 59)}%`);
    root.style.setProperty('--theme-sidebar-primary-lightness', asString(t.theme_sidebar_primary_lightness, 41));
    root.style.setProperty(
        '--theme-sidebar-primary-dark-mode-lightness',
        asString(t.theme_sidebar_primary_dark_mode_lightness, 55),
    );

    root.style.setProperty('--theme-sidebar-accent-hue', asString(t.theme_sidebar_accent_hue, 215));
    root.style.setProperty('--theme-sidebar-accent-saturation', `${asString(t.theme_sidebar_accent_saturation, 59)}%`);
    root.style.setProperty('--theme-sidebar-accent-lightness', asString(t.theme_sidebar_accent_lightness, 95));

    root.style.setProperty(
        '--theme-background-light-mode-lightness',
        `${asString(t.theme_background_light_mode_lightness, 100)}%`,
    );
    root.style.setProperty(
        '--theme-background-dark-mode-lightness',
        `${asString(t.theme_background_dark_mode_lightness, 3)}%`,
    );

    root.style.setProperty('--theme-layout-sidebar-width', asString(t.theme_layout_sidebar_width, '260px'));
    root.style.setProperty(
        '--theme-layout-sidebar-collapsed-width',
        asString(t.theme_layout_sidebar_collapsed_width, '64px'),
    );
    root.style.setProperty('--theme-layout-header-height', asString(t.theme_layout_header_height, '64px'));
    root.style.setProperty('--theme-layout-border-radius', asString(t.theme_layout_border_radius, '0.5rem'));
    root.style.setProperty('--theme-layout-spacing-unit', asString(t.theme_layout_spacing_unit, '1rem'));
}
