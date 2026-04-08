import '../css/app.css';
import 'vue-sonner/style.css';
import 'flatpickr/dist/flatpickr.min.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import FlashToasts from '@/components/FlashToasts.vue';
import { Toaster } from 'vue-sonner';
import type { DefineComponent } from 'vue';
import { Fragment, createApp, h } from 'vue';
import flatpickr from 'flatpickr';
import { initializeTheme } from './composables/useAppearance';
import { buildDateTimeOptions, type DateTimeFormatProps } from './composables/useDateTimeFormat';

const appName = import.meta.env.VITE_APP_NAME || 'JobCardOnline';
type DateMethod = 'toLocaleDateString' | 'toLocaleTimeString' | 'toLocaleString';
const originalDateMethods: Partial<Record<DateMethod, Date[DateMethod]>> = {};
let activeDateTimeFormat: DateTimeFormatProps | undefined;
let dateInputMutationObserver: MutationObserver | null = null;
let dateInputRefreshTimer: number | null = null;

function inputDateFormatHint(format: DateTimeFormatProps | undefined): string {
    switch (format?.date_format) {
        case 'mm/dd/yyyy':
            return 'MM/DD/YYYY';
        case 'yyyy-mm-dd':
            return 'YYYY-MM-DD';
        case 'd mmm yyyy':
            return 'D MMM YYYY';
        case 'dd/mm/yyyy':
        default:
            return 'DD/MM/YYYY';
    }
}

function inputTimeFormatHint(format: DateTimeFormatProps | undefined): string {
    return format?.time_format === '12h' ? 'hh:mm AM/PM' : 'HH:mm';
}

function resolveFlatpickrDateAltFormat(format: DateTimeFormatProps | undefined): string {
    switch (format?.date_format) {
        case 'mm/dd/yyyy':
            return 'm/d/Y';
        case 'yyyy-mm-dd':
            return 'Y-m-d';
        case 'd mmm yyyy':
            return 'j M Y';
        case 'dd/mm/yyyy':
        default:
            return 'd/m/Y';
    }
}

function resolveFlatpickrTimeAltFormat(format: DateTimeFormatProps | undefined): string {
    return format?.time_format === '12h' ? 'h:i K' : 'H:i';
}

function createFlatpickrForInput(
    input: HTMLInputElement,
    format: DateTimeFormatProps | undefined,
    isDateTime: boolean,
): void {
    if ((input as any)._flatpickr) {
        (input as any)._flatpickr.destroy();
    }

    const dateAlt = resolveFlatpickrDateAltFormat(format);
    const timeAlt = resolveFlatpickrTimeAltFormat(format);
    const dateHint = inputDateFormatHint(format);
    const dateTimeHint = `${dateHint} ${inputTimeFormatHint(format)}`;

    input.setAttribute('title', isDateTime ? dateTimeHint : dateHint);
    flatpickr(input, {
        allowInput: true,
        altInput: true,
        altFormat: isDateTime ? `${dateAlt} ${timeAlt}` : dateAlt,
        dateFormat: isDateTime ? 'Y-m-d\\TH:i' : 'Y-m-d',
        enableTime: isDateTime,
        noCalendar: false,
        time_24hr: format?.time_format !== '12h',
        disableMobile: true,
        minuteIncrement: 1,
        defaultHour: 0,
        defaultMinute: 0,
        onChange: () => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
        onClose: () => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        },
    });
}

function applyDateInputLocalization(format: DateTimeFormatProps | undefined): void {
    const resolved = buildDateTimeOptions(format);

    document.documentElement.setAttribute('lang', resolved.locale);
    document.querySelectorAll<HTMLInputElement>('input[type="date"]').forEach((input) => {
        input.setAttribute('lang', resolved.locale);
        createFlatpickrForInput(input, format, false);
    });
    document.querySelectorAll<HTMLInputElement>('input[type="datetime-local"]').forEach((input) => {
        input.setAttribute('lang', resolved.locale);
        createFlatpickrForInput(input, format, true);
    });
}

function scheduleDateInputLocalization(format: DateTimeFormatProps | undefined): void {
    if (dateInputRefreshTimer !== null) {
        window.clearTimeout(dateInputRefreshTimer);
    }
    dateInputRefreshTimer = window.setTimeout(() => {
        applyDateInputLocalization(format);
    }, 0);
}

function patchDateLocalization(format: DateTimeFormatProps | undefined): void {
    const resolved = buildDateTimeOptions(format);

    (['toLocaleDateString', 'toLocaleTimeString', 'toLocaleString'] as DateMethod[]).forEach((method) => {
        if (!originalDateMethods[method]) {
            originalDateMethods[method] = Date.prototype[method];
        }
        const original = originalDateMethods[method];
        if (!original) return;

        Date.prototype[method] = function (_locales?: Intl.LocalesArgument, options?: Intl.DateTimeFormatOptions) {
            return original.call(this, resolved.locale, {
                ...options,
                timeZone: resolved.timezone,
                hour12: method === 'toLocaleDateString' ? undefined : resolved.hour12,
            });
        };
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialProps = (props?.initialPage?.props ?? {}) as { dateTimeFormat?: DateTimeFormatProps };
        activeDateTimeFormat = initialProps.dateTimeFormat;
        patchDateLocalization(initialProps.dateTimeFormat);
        applyDateInputLocalization(initialProps.dateTimeFormat);
        requestAnimationFrame(() => applyDateInputLocalization(initialProps.dateTimeFormat));
        setTimeout(() => applyDateInputLocalization(initialProps.dateTimeFormat), 100);

        document.addEventListener('focusin', (event) => {
            const target = event.target as HTMLElement | null;
            if (!(target instanceof HTMLInputElement)) return;
            if (target.type !== 'date' && target.type !== 'datetime-local') return;
            createFlatpickrForInput(target, activeDateTimeFormat, target.type === 'datetime-local');
        });

        if (dateInputMutationObserver) {
            dateInputMutationObserver.disconnect();
        }
        dateInputMutationObserver = new MutationObserver(() => {
            scheduleDateInputLocalization(activeDateTimeFormat);
        });
        dateInputMutationObserver.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['type', 'value'],
        });

        router.on('navigate', (event) => {
            const nextProps = (event.detail.page.props ?? {}) as { dateTimeFormat?: DateTimeFormatProps };
            activeDateTimeFormat = nextProps.dateTimeFormat;
            patchDateLocalization(nextProps.dateTimeFormat);
            applyDateInputLocalization(nextProps.dateTimeFormat);
            requestAnimationFrame(() => applyDateInputLocalization(nextProps.dateTimeFormat));
            setTimeout(() => applyDateInputLocalization(nextProps.dateTimeFormat), 100);
        });

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
