import '../css/app.css';
import 'vue-sonner/style.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FlashToasts from '@/components/FlashToasts.vue';
import { Toaster } from 'vue-sonner';
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'JobCardOnline';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () =>
                h(Fragment, [
                    h(App, props),
                    h(Toaster, {
                        closeButton: true,
                        position: 'top-right',
                        richColors: true,
                        theme: 'system',
                    }),
                    h(FlashToasts),
                ]),
        })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// Global error handler for Inertia
router.on('error', (event) => {
    console.error('Inertia error:', event.detail);
    
    // Handle specific error types
    if (event.detail.message?.includes('Cannot read properties of null')) {
        console.warn('Null reference error detected, this may be due to missing data or route issues');
        // Don't show error to user for null reference errors as they're usually handled gracefully
        return;
    }
    
    // For other errors, you might want to show a user-friendly message
    // or redirect to an error page
});
