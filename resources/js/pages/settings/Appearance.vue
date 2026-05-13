<script setup lang="ts">
import { Head, usePage, useForm, router } from '@inertiajs/vue3';
import { computed, ref, onUnmounted } from 'vue';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';

const page = usePage();
const company = computed(() => page.props.company as any);

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
    theme_primary_hue: company.value?.theme_primary_hue ?? 215,
    theme_primary_saturation: company.value?.theme_primary_saturation ?? 59,
    theme_primary_lightness: company.value?.theme_primary_lightness ?? 41,
    theme_primary_dark_mode_lightness: company.value?.theme_primary_dark_mode_lightness ?? 55,
    theme_secondary_hue: company.value?.theme_secondary_hue ?? 178,
    theme_secondary_saturation: company.value?.theme_secondary_saturation ?? 62,
    theme_secondary_lightness: company.value?.theme_secondary_lightness ?? 46,
    theme_secondary_dark_mode_lightness: company.value?.theme_secondary_dark_mode_lightness ?? 55,
    theme_accent_hue: company.value?.theme_accent_hue ?? 215,
    theme_accent_saturation: company.value?.theme_accent_saturation ?? 48,
    theme_accent_lightness: company.value?.theme_accent_lightness ?? 48,
    theme_accent_dark_mode_lightness: company.value?.theme_accent_dark_mode_lightness ?? 60,
    theme_background_light_mode_lightness: company.value?.theme_background_light_mode_lightness ?? 100,
    theme_background_dark_mode_lightness: company.value?.theme_background_dark_mode_lightness ?? 3,
    theme_layout_sidebar_width: company.value?.theme_layout_sidebar_width ?? '260px',
    theme_layout_sidebar_collapsed_width: company.value?.theme_layout_sidebar_collapsed_width ?? '64px',
    theme_layout_header_height: company.value?.theme_layout_header_height ?? '64px',
    theme_layout_border_radius: company.value?.theme_layout_border_radius ?? '0.5rem',
    theme_layout_spacing_unit: company.value?.theme_layout_spacing_unit ?? '1rem',
});

// Separate refs for files
const logoFile = ref<File | null>(null);
const faviconFile = ref<File | null>(null);
const logoPreview = ref<string>('');
const faviconPreview = ref<string>('');

// Helper to get image URL
const getImageUrl = (path: string | null | undefined): string => {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    if (path.startsWith('blob:')) return path;
    return `/storage/${path}`;
};

// Set initial previews from existing data
if (company.value?.logo_path) {
    logoPreview.value = getImageUrl(company.value.logo_path);
}
if (company.value?.favicon_path) {
    faviconPreview.value = getImageUrl(company.value.favicon_path);
}

const handleLogoUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Logo file size must be less than 2MB');
            target.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/x-icon', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Please upload JPEG, PNG, GIF, SVG, or ICO files.');
            target.value = '';
            return;
        }
        
        logoFile.value = file;
        
        // Create preview
        if (logoPreview.value && !logoPreview.value.startsWith('/storage/')) {
            URL.revokeObjectURL(logoPreview.value);
        }
        logoPreview.value = URL.createObjectURL(file);
        
        console.log('Logo selected:', file.name, file.size, file.type);
    }
};

const handleFaviconUpload = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const file = target.files?.[0];
    
    if (file) {
        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Favicon file size must be less than 2MB');
            target.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/svg+xml', 'image/x-icon', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Invalid file type. Please upload JPEG, PNG, GIF, SVG, or ICO files.');
            target.value = '';
            return;
        }
        
        faviconFile.value = file;
        
        // Create preview
        if (faviconPreview.value && !faviconPreview.value.startsWith('/storage/')) {
            URL.revokeObjectURL(faviconPreview.value);
        }
        faviconPreview.value = URL.createObjectURL(file);
        
        console.log('Favicon selected:', file.name, file.size, file.type);
    }
};

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
    company.value?.theme_primary_hue ?? 215,
    company.value?.theme_primary_saturation ?? 59,
    company.value?.theme_primary_lightness ?? 41
));

const secondaryColor = ref(hslToHex(
    company.value?.theme_secondary_hue ?? 178,
    company.value?.theme_secondary_saturation ?? 62,
    company.value?.theme_secondary_lightness ?? 46
));

const accentColor = ref(hslToHex(
    company.value?.theme_accent_hue ?? 215,
    company.value?.theme_accent_saturation ?? 48,
    company.value?.theme_accent_lightness ?? 48
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

const submit = () => {
    console.log('Submitting form...');
    console.log('Logo file:', logoFile.value);
    console.log('Favicon file:', faviconFile.value);
    
    // Create FormData manually
    const formData = new FormData();
    
    // Add _method for PATCH spoofing
    formData.append('_method', 'PATCH');
    
    // Add all form fields - manually list them to avoid TypeScript errors
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
    formData.append('theme_background_light_mode_lightness', form.theme_background_light_mode_lightness.toString());
    formData.append('theme_background_dark_mode_lightness', form.theme_background_dark_mode_lightness.toString());
    formData.append('theme_layout_sidebar_width', form.theme_layout_sidebar_width);
    formData.append('theme_layout_sidebar_collapsed_width', form.theme_layout_sidebar_collapsed_width);
    formData.append('theme_layout_header_height', form.theme_layout_header_height);
    formData.append('theme_layout_border_radius', form.theme_layout_border_radius);
    formData.append('theme_layout_spacing_unit', form.theme_layout_spacing_unit);
    
    // Add files
    if (logoFile.value) {
        formData.append('logo', logoFile.value);
    }
    if (faviconFile.value) {
        formData.append('favicon', faviconFile.value);
    }
    
    // Log what we're sending
    console.log('Sending FormData with fields:', Array.from(formData.keys()));
    
    // Send using router.post (Inertia v2)
    router.post('/settings/appearance', formData, {
        preserveScroll: true,
        onSuccess: () => {
            console.log('Success!');
            // Reset file inputs
            logoFile.value = null;
            faviconFile.value = null;
            const logoInput = document.getElementById('logo') as HTMLInputElement;
            const faviconInput = document.getElementById('favicon') as HTMLInputElement;
            if (logoInput) logoInput.value = '';
            if (faviconInput) faviconInput.value = '';
            
            // Refresh page to show new images after a short delay
            setTimeout(() => {
                window.location.reload();
            }, 500);
        },
        onError: (errors) => {
            console.error('Error:', errors);
            alert('Error: ' + JSON.stringify(errors));
        },
    });
};

// Clean up on unmount
onUnmounted(() => {
    if (logoPreview.value && logoPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(logoPreview.value);
    }
    if (faviconPreview.value && faviconPreview.value.startsWith('blob:')) {
        URL.revokeObjectURL(faviconPreview.value);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <!-- Debug info (remove in production) -->
                <div v-if="logoFile || faviconFile" class="rounded-lg border border-blue-500 bg-blue-50 p-4">
                    <h3 class="mb-2 font-bold text-blue-800">Files Ready to Upload</h3>
                    <p v-if="logoFile" class="text-sm text-blue-700">Logo: {{ logoFile.name }} ({{ (logoFile.size / 1024).toFixed(2) }} KB)</p>
                    <p v-if="faviconFile" class="text-sm text-blue-700">Favicon: {{ faviconFile.name }} ({{ (faviconFile.size / 1024).toFixed(2) }} KB)</p>
                </div>

                <HeadingSmall
                    title="Appearance settings"
                    description="Customize your company's theme colors and branding"
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

                    <!-- Branding Section -->
                    <div class="rounded-lg border p-6">
                        <h3 class="mb-4 text-lg font-semibold">Branding</h3>
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h4 class="font-medium">Logo</h4>
                                <div>
                                    <Label for="logo">Upload Logo</Label>
                                    <Input
                                        id="logo"
                                        type="file"
                                        @change="handleLogoUpload"
                                        accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml,image/x-icon,image/webp"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Recommended size: 200x60px. Max file size: 2MB.
                                    </p>
                                </div>
                                <div v-if="logoPreview" class="mt-2">
                                    <p class="text-sm text-muted-foreground">Logo preview:</p>
                                    <img
                                        :src="logoPreview"
                                        alt="Company logo"
                                        class="mt-1 h-16 rounded border object-contain"
                                    />
                                </div>
                            </div>

                            <div class="space-y-4">
                                <h4 class="font-medium">Favicon</h4>
                                <div>
                                    <Label for="favicon">Upload Favicon</Label>
                                    <Input
                                        id="favicon"
                                        type="file"
                                        @change="handleFaviconUpload"
                                        accept="image/jpeg,image/png,image/jpg,image/gif,image/svg+xml,image/x-icon,image/webp"
                                        class="mt-1"
                                    />
                                    <p class="mt-1 text-sm text-muted-foreground">
                                        Recommended size: 32x32px or 16x16px. Max file size: 2MB.
                                    </p>
                                </div>
                                <div v-if="faviconPreview" class="mt-2">
                                    <p class="text-sm text-muted-foreground">Favicon preview:</p>
                                    <img
                                        :src="faviconPreview"
                                        alt="Company favicon"
                                        class="mt-1 h-8 w-8 rounded border object-contain"
                                    />
                                </div>
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