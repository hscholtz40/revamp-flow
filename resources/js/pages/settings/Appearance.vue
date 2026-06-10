<script setup lang="ts">
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const page = usePage();
const company = computed(() => page.props.company as Record<string, unknown> | undefined);

const companyThemeNumber = (key: string, fallback: number): number => {
    const value = company.value?.[key];
    return typeof value === 'number' ? value : fallback;
};

const companyThemeString = (key: string, fallback: string): string => {
    const value = company.value?.[key];
    return typeof value === 'string' ? value : fallback;
};

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: '/settings/appearance',
    },
];

// Define the form data type
interface AppearanceFormData {
    theme_primary_hue: number;
    theme_primary_saturation: number;
    theme_primary_lightness: number;
    theme_primary_dark_mode_lightness: number;
    theme_secondary_hue: number;
    theme_secondary_saturation: number;
    theme_secondary_lightness: number;
    theme_secondary_dark_mode_lightness: number;
    theme_accent_hue: number;
    theme_accent_saturation: number;
    theme_accent_lightness: number;
    theme_accent_dark_mode_lightness: number;
    theme_chart_1_hue: number;
    theme_chart_1_saturation: number;
    theme_chart_1_lightness: number;
    theme_chart_2_hue: number;
    theme_chart_2_saturation: number;
    theme_chart_2_lightness: number;
    theme_chart_3_hue: number;
    theme_chart_3_saturation: number;
    theme_chart_3_lightness: number;
    theme_chart_4_hue: number;
    theme_chart_4_saturation: number;
    theme_chart_4_lightness: number;
    theme_chart_5_hue: number;
    theme_chart_5_saturation: number;
    theme_chart_5_lightness: number;
    theme_sidebar_primary_hue: number;
    theme_sidebar_primary_saturation: number;
    theme_sidebar_primary_lightness: number;
    theme_sidebar_primary_dark_mode_lightness: number;
    theme_sidebar_accent_hue: number;
    theme_sidebar_accent_saturation: number;
    theme_sidebar_accent_lightness: number;
    theme_background_light_mode_lightness: number;
    theme_background_dark_mode_lightness: number;
    theme_layout_sidebar_width: string;
    theme_layout_sidebar_collapsed_width: string;
    theme_layout_header_height: string;
    theme_layout_border_radius: string;
    theme_layout_spacing_unit: string;
}

// Initialize form with current values
const form = useForm<AppearanceFormData>({
    theme_primary_hue: companyThemeNumber('theme_primary_hue', 215),
    theme_primary_saturation: companyThemeNumber('theme_primary_saturation', 59),
    theme_primary_lightness: companyThemeNumber('theme_primary_lightness', 41),
    theme_primary_dark_mode_lightness: companyThemeNumber('theme_primary_dark_mode_lightness', 55),
    theme_secondary_hue: companyThemeNumber('theme_secondary_hue', 178),
    theme_secondary_saturation: companyThemeNumber('theme_secondary_saturation', 62),
    theme_secondary_lightness: companyThemeNumber('theme_secondary_lightness', 46),
    theme_secondary_dark_mode_lightness: companyThemeNumber('theme_secondary_dark_mode_lightness', 55),
    theme_accent_hue: companyThemeNumber('theme_accent_hue', 215),
    theme_accent_saturation: companyThemeNumber('theme_accent_saturation', 48),
    theme_accent_lightness: companyThemeNumber('theme_accent_lightness', 48),
    theme_accent_dark_mode_lightness: companyThemeNumber('theme_accent_dark_mode_lightness', 60),
    theme_chart_1_hue: companyThemeNumber('theme_chart_1_hue', 215),
    theme_chart_1_saturation: companyThemeNumber('theme_chart_1_saturation', 59),
    theme_chart_1_lightness: companyThemeNumber('theme_chart_1_lightness', 41),
    theme_chart_2_hue: companyThemeNumber('theme_chart_2_hue', 178),
    theme_chart_2_saturation: companyThemeNumber('theme_chart_2_saturation', 62),
    theme_chart_2_lightness: companyThemeNumber('theme_chart_2_lightness', 46),
    theme_chart_3_hue: companyThemeNumber('theme_chart_3_hue', 215),
    theme_chart_3_saturation: companyThemeNumber('theme_chart_3_saturation', 48),
    theme_chart_3_lightness: companyThemeNumber('theme_chart_3_lightness', 48),
    theme_chart_4_hue: companyThemeNumber('theme_chart_4_hue', 178),
    theme_chart_4_saturation: companyThemeNumber('theme_chart_4_saturation', 50),
    theme_chart_4_lightness: companyThemeNumber('theme_chart_4_lightness', 55),
    theme_chart_5_hue: companyThemeNumber('theme_chart_5_hue', 215),
    theme_chart_5_saturation: companyThemeNumber('theme_chart_5_saturation', 55),
    theme_chart_5_lightness: companyThemeNumber('theme_chart_5_lightness', 55),
    theme_sidebar_primary_hue: companyThemeNumber('theme_sidebar_primary_hue', 215),
    theme_sidebar_primary_saturation: companyThemeNumber('theme_sidebar_primary_saturation', 59),
    theme_sidebar_primary_lightness: companyThemeNumber('theme_sidebar_primary_lightness', 41),
    theme_sidebar_primary_dark_mode_lightness: companyThemeNumber('theme_sidebar_primary_dark_mode_lightness', 55),
    theme_sidebar_accent_hue: companyThemeNumber('theme_sidebar_accent_hue', 215),
    theme_sidebar_accent_saturation: companyThemeNumber('theme_sidebar_accent_saturation', 59),
    theme_sidebar_accent_lightness: companyThemeNumber('theme_sidebar_accent_lightness', 95),
    theme_background_light_mode_lightness: companyThemeNumber('theme_background_light_mode_lightness', 100),
    theme_background_dark_mode_lightness: companyThemeNumber('theme_background_dark_mode_lightness', 3),
    theme_layout_sidebar_width: companyThemeString('theme_layout_sidebar_width', '260px'),
    theme_layout_sidebar_collapsed_width: companyThemeString('theme_layout_sidebar_collapsed_width', '64px'),
    theme_layout_header_height: companyThemeString('theme_layout_header_height', '64px'),
    theme_layout_border_radius: companyThemeString('theme_layout_border_radius', '0.5rem'),
    theme_layout_spacing_unit: companyThemeString('theme_layout_spacing_unit', '1rem'),
});

const getThemeColor = (hue: number, saturation: number, lightness: number): string => {
    return `hsl(${hue} ${saturation}% ${lightness}%)`;
};

const hslToHex = (h: number, s: number, l: number): string => {
    s /= 100;
    l /= 100;
    const a = s * Math.min(l, 1 - l);
    const f = (n: number) => {
        const k = (n + h / 30) % 12;
        const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
        return Math.round(255 * color)
            .toString(16)
            .padStart(2, '0');
    };
    return `#${f(0)}${f(8)}${f(4)}`;
};

const primaryColor = ref(hslToHex(
    companyThemeNumber('theme_primary_hue', 215),
    companyThemeNumber('theme_primary_saturation', 59),
    companyThemeNumber('theme_primary_lightness', 41),
));

const secondaryColor = ref(hslToHex(
    companyThemeNumber('theme_secondary_hue', 178),
    companyThemeNumber('theme_secondary_saturation', 62),
    companyThemeNumber('theme_secondary_lightness', 46),
));

const accentColor = ref(hslToHex(
    companyThemeNumber('theme_accent_hue', 215),
    companyThemeNumber('theme_accent_saturation', 48),
    companyThemeNumber('theme_accent_lightness', 48),
));

const hexToHsl = (hex: string): { h: number; s: number; l: number } => {
    let r = 0, g = 0, b = 0;
    if (hex.length === 4) {
        r = parseInt(hex[1] + hex[1], 16);
        g = parseInt(hex[2] + hex[2], 16);
        b = parseInt(hex[3] + hex[3], 16);
    } else if (hex.length === 7) {
        r = parseInt(hex.substring(1, 3), 16);
        g = parseInt(hex.substring(3, 5), 16);
        b = parseInt(hex.substring(5, 7), 16);
    }
    
    r /= 255;
    g /= 255;
    b /= 255;
    
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0, s = 0;
    const l = (max + min) / 2;
    
    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        
        switch (max) {
            case r:
                h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
                break;
            case g:
                h = ((b - r) / d + 2) / 6;
                break;
            case b:
                h = ((r - g) / d + 4) / 6;
                break;
        }
    }
    
    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100)
    };
};

const updatePrimaryColor = (hex: string) => {
    const hsl = hexToHsl(hex);
    primaryColor.value = hex;
    form.theme_primary_hue = hsl.h;
    form.theme_primary_saturation = hsl.s;
    form.theme_primary_lightness = hsl.l;
};

const updateSecondaryColor = (hex: string) => {
    const hsl = hexToHsl(hex);
    secondaryColor.value = hex;
    form.theme_secondary_hue = hsl.h;
    form.theme_secondary_saturation = hsl.s;
    form.theme_secondary_lightness = hsl.l;
};

const updateAccentColor = (hex: string) => {
    const hsl = hexToHsl(hex);
    accentColor.value = hex;
    form.theme_accent_hue = hsl.h;
    form.theme_accent_saturation = hsl.s;
    form.theme_accent_lightness = hsl.l;
};

// Function to update CSS variables dynamically
const updateCssVariables = () => {
    const root = document.documentElement;
    
    // Primary colors
    root.style.setProperty('--theme-primary-hue', form.theme_primary_hue.toString());
    root.style.setProperty('--theme-primary-saturation', `${form.theme_primary_saturation}%`);
    root.style.setProperty('--theme-primary-lightness', `${form.theme_primary_lightness}%`);
    root.style.setProperty('--theme-primary-dark-mode-lightness', form.theme_primary_dark_mode_lightness.toString());
    
    // Secondary colors
    root.style.setProperty('--theme-secondary-hue', form.theme_secondary_hue.toString());
    root.style.setProperty('--theme-secondary-saturation', `${form.theme_secondary_saturation}%`);
    root.style.setProperty('--theme-secondary-lightness', `${form.theme_secondary_lightness}%`);
    root.style.setProperty('--theme-secondary-dark-mode-lightness', form.theme_secondary_dark_mode_lightness.toString());
    
    // Accent colors
    root.style.setProperty('--theme-accent-hue', form.theme_accent_hue.toString());
    root.style.setProperty('--theme-accent-saturation', `${form.theme_accent_saturation}%`);
    root.style.setProperty('--theme-accent-lightness', `${form.theme_accent_lightness}%`);
    root.style.setProperty('--theme-accent-dark-mode-lightness', form.theme_accent_dark_mode_lightness.toString());
    
    // Chart colors
    root.style.setProperty('--theme-chart-1-hue', form.theme_chart_1_hue.toString());
    root.style.setProperty('--theme-chart-1-saturation', `${form.theme_chart_1_saturation}%`);
    root.style.setProperty('--theme-chart-1-lightness', form.theme_chart_1_lightness.toString());
    
    root.style.setProperty('--theme-chart-2-hue', form.theme_chart_2_hue.toString());
    root.style.setProperty('--theme-chart-2-saturation', `${form.theme_chart_2_saturation}%`);
    root.style.setProperty('--theme-chart-2-lightness', form.theme_chart_2_lightness.toString());
    
    root.style.setProperty('--theme-chart-3-hue', form.theme_chart_3_hue.toString());
    root.style.setProperty('--theme-chart-3-saturation', `${form.theme_chart_3_saturation}%`);
    root.style.setProperty('--theme-chart-3-lightness', form.theme_chart_3_lightness.toString());
    
    root.style.setProperty('--theme-chart-4-hue', form.theme_chart_4_hue.toString());
    root.style.setProperty('--theme-chart-4-saturation', `${form.theme_chart_4_saturation}%`);
    root.style.setProperty('--theme-chart-4-lightness', form.theme_chart_4_lightness.toString());
    
    root.style.setProperty('--theme-chart-5-hue', form.theme_chart_5_hue.toString());
    root.style.setProperty('--theme-chart-5-saturation', `${form.theme_chart_5_saturation}%`);
    root.style.setProperty('--theme-chart-5-lightness', form.theme_chart_5_lightness.toString());
    
    // Sidebar colors
    root.style.setProperty('--theme-sidebar-primary-hue', form.theme_sidebar_primary_hue.toString());
    root.style.setProperty('--theme-sidebar-primary-saturation', `${form.theme_sidebar_primary_saturation}%`);
    root.style.setProperty('--theme-sidebar-primary-lightness', `${form.theme_sidebar_primary_lightness}%`);
    root.style.setProperty('--theme-sidebar-primary-dark-mode-lightness', form.theme_sidebar_primary_dark_mode_lightness.toString());
    
    root.style.setProperty('--theme-sidebar-accent-hue', form.theme_sidebar_accent_hue.toString());
    root.style.setProperty('--theme-sidebar-accent-saturation', `${form.theme_sidebar_accent_saturation}%`);
    root.style.setProperty('--theme-sidebar-accent-lightness', `${form.theme_sidebar_accent_lightness}%`);
    
    // Background colors
    root.style.setProperty('--theme-background-light-mode-lightness', `${form.theme_background_light_mode_lightness}%`);
    root.style.setProperty('--theme-background-dark-mode-lightness', `${form.theme_background_dark_mode_lightness}%`);
    
    // Layout variables
    root.style.setProperty('--theme-layout-sidebar-width', form.theme_layout_sidebar_width);
    root.style.setProperty('--theme-layout-sidebar-collapsed-width', form.theme_layout_sidebar_collapsed_width);
    root.style.setProperty('--theme-layout-header-height', form.theme_layout_header_height);
    root.style.setProperty('--theme-layout-border-radius', form.theme_layout_border_radius);
    root.style.setProperty('--theme-layout-spacing-unit', form.theme_layout_spacing_unit);
};

const submit = () => {
    console.log('Submitting form...');

    // Create FormData manually
    const formData = new FormData();

    // Add _method for PATCH spoofing
    formData.append('_method', 'PATCH');

    // Add all form fields
    formData.append('theme_primary_hue', form.theme_primary_hue.toString());
    formData.append('theme_primary_saturation', form.theme_primary_saturation.toString());
    formData.append('theme_primary_lightness', form.theme_primary_lightness.toString());
    formData.append('theme_primary_dark_mode_lightness', form.theme_primary_dark_mode_lightness.toString());
    formData.append('theme_secondary_hue', form.theme_secondary_hue.toString());
    formData.append('theme_secondary_saturation', form.theme_secondary_saturation.toString());
    formData.append('theme_secondary_lightness', form.theme_secondary_lightness.toString());
    formData.append('theme_secondary_dark_mode_lightness', form.theme_secondary_dark_mode_lightness.toString());
    formData.append('theme_accent_hue', form.theme_accent_hue.toString());
    formData.append('theme_accent_saturation', form.theme_accent_saturation.toString());
    formData.append('theme_accent_lightness', form.theme_accent_lightness.toString());
    formData.append('theme_accent_dark_mode_lightness', form.theme_accent_dark_mode_lightness.toString());
    formData.append('theme_chart_1_hue', form.theme_chart_1_hue.toString());
    formData.append('theme_chart_1_saturation', form.theme_chart_1_saturation.toString());
    formData.append('theme_chart_1_lightness', form.theme_chart_1_lightness.toString());
    formData.append('theme_chart_2_hue', form.theme_chart_2_hue.toString());
    formData.append('theme_chart_2_saturation', form.theme_chart_2_saturation.toString());
    formData.append('theme_chart_2_lightness', form.theme_chart_2_lightness.toString());
    formData.append('theme_chart_3_hue', form.theme_chart_3_hue.toString());
    formData.append('theme_chart_3_saturation', form.theme_chart_3_saturation.toString());
    formData.append('theme_chart_3_lightness', form.theme_chart_3_lightness.toString());
    formData.append('theme_chart_4_hue', form.theme_chart_4_hue.toString());
    formData.append('theme_chart_4_saturation', form.theme_chart_4_saturation.toString());
    formData.append('theme_chart_4_lightness', form.theme_chart_4_lightness.toString());
    formData.append('theme_chart_5_hue', form.theme_chart_5_hue.toString());
    formData.append('theme_chart_5_saturation', form.theme_chart_5_saturation.toString());
    formData.append('theme_chart_5_lightness', form.theme_chart_5_lightness.toString());
    formData.append('theme_sidebar_primary_hue', form.theme_sidebar_primary_hue.toString());
    formData.append('theme_sidebar_primary_saturation', form.theme_sidebar_primary_saturation.toString());
    formData.append('theme_sidebar_primary_lightness', form.theme_sidebar_primary_lightness.toString());
    formData.append('theme_sidebar_primary_dark_mode_lightness', form.theme_sidebar_primary_dark_mode_lightness.toString());
    formData.append('theme_sidebar_accent_hue', form.theme_sidebar_accent_hue.toString());
    formData.append('theme_sidebar_accent_saturation', form.theme_sidebar_accent_saturation.toString());
    formData.append('theme_sidebar_accent_lightness', form.theme_sidebar_accent_lightness.toString());
    formData.append('theme_background_light_mode_lightness', form.theme_background_light_mode_lightness.toString());
    formData.append('theme_background_dark_mode_lightness', form.theme_background_dark_mode_lightness.toString());
    formData.append('theme_layout_sidebar_width', form.theme_layout_sidebar_width);
    formData.append('theme_layout_sidebar_collapsed_width', form.theme_layout_sidebar_collapsed_width);
    formData.append('theme_layout_header_height', form.theme_layout_header_height);
    formData.append('theme_layout_border_radius', form.theme_layout_border_radius);
    formData.append('theme_layout_spacing_unit', form.theme_layout_spacing_unit);

    // Send using router.post
    router.post('/settings/appearance', formData, {
        preserveScroll: true,
        onSuccess: () => {
            console.log('Success!');
            
            // Update CSS variables dynamically without reload
            updateCssVariables();
            
            // Update the color picker values to match
            primaryColor.value = hslToHex(
                form.theme_primary_hue,
                form.theme_primary_saturation,
                form.theme_primary_lightness
            );
            secondaryColor.value = hslToHex(
                form.theme_secondary_hue,
                form.theme_secondary_saturation,
                form.theme_secondary_lightness
            );
            accentColor.value = hslToHex(
                form.theme_accent_hue,
                form.theme_accent_saturation,
                form.theme_accent_lightness
            );
            
            // Optional: Show success message
            // You can add a toast notification here
        },
        onError: (errors) => {
            console.error('Error:', errors);
            alert('Error: ' + JSON.stringify(errors));
        },
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Appearance settings"
                    description="Customize theme colors for your current company"
                />

                <AppearanceTabs />

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- Colors Section -->
                    <div class="rounded-lg border p-6">
                        <h3 class="mb-4 text-lg font-semibold">Colors</h3>

                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h4 class="font-medium">Primary Color</h4>
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0">
                                        <input
                                            type="color"
                                            :value="primaryColor"
                                            @input="updatePrimaryColor(($event.target as HTMLInputElement).value)"
                                            class="h-12 w-12 cursor-pointer rounded border"
                                        />
                                    </div>
                                    <div class="flex-grow grid grid-cols-2 gap-4">
                                        <div>
                                            <Label for="theme_primary_hue">Hue (0-360)</Label>
                                            <Input
                                                id="theme_primary_hue"
                                                type="number"
                                                v-model.number="form.theme_primary_hue"
                                                min="0"
                                                max="360"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_primary_saturation">Saturation (0-100%)</Label>
                                            <Input
                                                id="theme_primary_saturation"
                                                type="number"
                                                v-model.number="form.theme_primary_saturation"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_primary_lightness">Lightness (0-100%)</Label>
                                            <Input
                                                id="theme_primary_lightness"
                                                type="number"
                                                v-model.number="form.theme_primary_lightness"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_primary_dark_mode_lightness">Dark Mode Lightness</Label>
                                            <Input
                                                id="theme_primary_dark_mode_lightness"
                                                type="number"
                                                v-model.number="form.theme_primary_dark_mode_lightness"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="h-12 rounded border"
                                    :style="{
                                        backgroundColor: getThemeColor(
                                            form.theme_primary_hue,
                                            form.theme_primary_saturation,
                                            form.theme_primary_lightness
                                        ),
                                    }"
                                ></div>
                            </div>

                            <div class="space-y-4">
                                <h4 class="font-medium">Secondary Color</h4>
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0">
                                        <input
                                            type="color"
                                            :value="secondaryColor"
                                            @input="updateSecondaryColor(($event.target as HTMLInputElement).value)"
                                            class="h-12 w-12 cursor-pointer rounded border"
                                        />
                                    </div>
                                    <div class="flex-grow grid grid-cols-2 gap-4">
                                        <div>
                                            <Label for="theme_secondary_hue">Hue (0-360)</Label>
                                            <Input
                                                id="theme_secondary_hue"
                                                type="number"
                                                v-model.number="form.theme_secondary_hue"
                                                min="0"
                                                max="360"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_secondary_saturation">Saturation (0-100%)</Label>
                                            <Input
                                                id="theme_secondary_saturation"
                                                type="number"
                                                v-model.number="form.theme_secondary_saturation"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_secondary_lightness">Lightness (0-100%)</Label>
                                            <Input
                                                id="theme_secondary_lightness"
                                                type="number"
                                                v-model.number="form.theme_secondary_lightness"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_secondary_dark_mode_lightness">Dark Mode Lightness</Label>
                                            <Input
                                                id="theme_secondary_dark_mode_lightness"
                                                type="number"
                                                v-model.number="form.theme_secondary_dark_mode_lightness"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="h-12 rounded border"
                                    :style="{
                                        backgroundColor: getThemeColor(
                                            form.theme_secondary_hue,
                                            form.theme_secondary_saturation,
                                            form.theme_secondary_lightness
                                        ),
                                    }"
                                ></div>
                            </div>

                            <div class="space-y-4">
                                <h4 class="font-medium">Accent Color</h4>
                                <div class="flex items-center gap-4">
                                    <div class="flex-shrink-0">
                                        <input
                                            type="color"
                                            :value="accentColor"
                                            @input="updateAccentColor(($event.target as HTMLInputElement).value)"
                                            class="h-12 w-12 cursor-pointer rounded border"
                                        />
                                    </div>
                                    <div class="flex-grow grid grid-cols-2 gap-4">
                                        <div>
                                            <Label for="theme_accent_hue">Hue (0-360)</Label>
                                            <Input
                                                id="theme_accent_hue"
                                                type="number"
                                                v-model.number="form.theme_accent_hue"
                                                min="0"
                                                max="360"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_accent_saturation">Saturation (0-100%)</Label>
                                            <Input
                                                id="theme_accent_saturation"
                                                type="number"
                                                v-model.number="form.theme_accent_saturation"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_accent_lightness">Lightness (0-100%)</Label>
                                            <Input
                                                id="theme_accent_lightness"
                                                type="number"
                                                v-model.number="form.theme_accent_lightness"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                        <div>
                                            <Label for="theme_accent_dark_mode_lightness">Dark Mode Lightness</Label>
                                            <Input
                                                id="theme_accent_dark_mode_lightness"
                                                type="number"
                                                v-model.number="form.theme_accent_dark_mode_lightness"
                                                min="0"
                                                max="100"
                                                class="mt-1"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="h-12 rounded border"
                                    :style="{
                                        backgroundColor: getThemeColor(
                                            form.theme_accent_hue,
                                            form.theme_accent_saturation,
                                            form.theme_accent_lightness
                                        ),
                                    }"
                                ></div>
                            </div>

                            <div class="space-y-4">
                                <h4 class="font-medium">Background Colors</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label for="theme_background_light_mode_lightness">Light Mode Lightness</Label>
                                        <Input
                                            id="theme_background_light_mode_lightness"
                                            type="number"
                                            v-model.number="form.theme_background_light_mode_lightness"
                                            min="0"
                                            max="100"
                                            class="mt-1"
                                        />
                                    </div>
                                    <div>
                                        <Label for="theme_background_dark_mode_lightness">Dark Mode Lightness</Label>
                                        <Input
                                            id="theme_background_dark_mode_lightness"
                                            type="number"
                                            v-model.number="form.theme_background_dark_mode_lightness"
                                            min="0"
                                            max="100"
                                            class="mt-1"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Layout Section -->
                    <div class="rounded-lg border p-6">
                        <h3 class="mb-4 text-lg font-semibold">Layout</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <Label for="theme_layout_sidebar_width">Sidebar Width</Label>
                                <Input
                                    id="theme_layout_sidebar_width"
                                    type="text"
                                    v-model="form.theme_layout_sidebar_width"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="theme_layout_sidebar_collapsed_width">Sidebar Collapsed Width</Label>
                                <Input
                                    id="theme_layout_sidebar_collapsed_width"
                                    type="text"
                                    v-model="form.theme_layout_sidebar_collapsed_width"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="theme_layout_header_height">Header Height</Label>
                                <Input
                                    id="theme_layout_header_height"
                                    type="text"
                                    v-model="form.theme_layout_header_height"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="theme_layout_border_radius">Border Radius</Label>
                                <Input
                                    id="theme_layout_border_radius"
                                    type="text"
                                    v-model="form.theme_layout_border_radius"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <Label for="theme_layout_spacing_unit">Spacing Unit</Label>
                                <Input
                                    id="theme_layout_spacing_unit"
                                    type="text"
                                    v-model="form.theme_layout_spacing_unit"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>