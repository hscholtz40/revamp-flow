import { parseUtcInstant } from '@/lib/dispatchScheduleFormat';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export interface DateTimeFormatProps {
    timezone: string;
    date_format: 'dd/mm/yyyy' | 'mm/dd/yyyy' | 'yyyy-mm-dd' | 'd mmm yyyy';
    time_format: '24h' | '12h';
}

function resolveLocaleForDateFormat(format: DateTimeFormatProps['date_format']): string {
    switch (format) {
        case 'mm/dd/yyyy':
            return 'en-US';
        case 'yyyy-mm-dd':
            return 'sv-SE';
        case 'd mmm yyyy':
            return 'en-GB';
        case 'dd/mm/yyyy':
        default:
            return 'en-GB';
    }
}

export function buildDateTimeOptions(
    format: DateTimeFormatProps | undefined,
): { locale: string; timezone: string; hour12: boolean } {
    const dateFormat = format?.date_format ?? 'dd/mm/yyyy';
    const timeFormat = format?.time_format ?? '24h';
    const timezone = format?.timezone || 'UTC';

    return {
        locale: resolveLocaleForDateFormat(dateFormat),
        timezone,
        hour12: timeFormat === '12h',
    };
}

function toValidDate(value: string | Date | null | undefined): Date | null {
    if (!value) return null;
    const date = value instanceof Date ? value : new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatDateValue(date: Date, format: DateTimeFormatProps): string {
    return new Intl.DateTimeFormat(resolveLocaleForDateFormat(format.date_format), {
        timeZone: format.timezone,
        year: 'numeric',
        month: format.date_format === 'd mmm yyyy' ? 'short' : '2-digit',
        day: '2-digit',
    }).format(date);
}

function formatTimeValue(date: Date, format: DateTimeFormatProps): string {
    return new Intl.DateTimeFormat(resolveLocaleForDateFormat(format.date_format), {
        timeZone: format.timezone,
        hour12: format.time_format === '12h',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function formatLocalizedDate(value: string | Date | null | undefined, format: DateTimeFormatProps): string {
    const date = toValidDate(value);
    if (!date) return 'N/A';
    return formatDateValue(date, format);
}

export function formatLocalizedTime(value: string | Date | null | undefined, format: DateTimeFormatProps): string {
    const date = toValidDate(value);
    if (!date) return 'N/A';
    return formatTimeValue(date, format);
}

export function formatLocalizedDateTime(value: string | Date | null | undefined, format: DateTimeFormatProps): string {
    const date = toValidDate(value);
    if (!date) return 'N/A';
    return `${formatDateValue(date, format)} ${formatTimeValue(date, format)}`;
}

/** Format a UTC API instant (`…Z` / `+00:00`) in the company timezone. */
export function formatUtcInstantInTimezone(utcIso: string, format: DateTimeFormatProps): string {
    const date = parseUtcInstant(utcIso);
    if (!date) {
        return '—';
    }

    const locale = resolveLocaleForDateFormat(format.date_format);
    const parts = new Intl.DateTimeFormat(locale, {
        timeZone: format.timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: format.time_format === '12h',
    }).formatToParts(date);

    const pick = (type: Intl.DateTimeFormatPartTypes) => parts.find((p) => p.type === type)?.value ?? '';

    if (format.date_format === 'yyyy-mm-dd') {
        return `${pick('year')}-${pick('month')}-${pick('day')} ${pick('hour')}:${pick('minute')}`;
    }
    if (format.date_format === 'mm/dd/yyyy') {
        return `${pick('month')}/${pick('day')}/${pick('year')} ${pick('hour')}:${pick('minute')}`;
    }

    return `${pick('day')}/${pick('month')}/${pick('year')} ${pick('hour')}:${pick('minute')}`;
}

/** Relative “ago” label for a UTC API instant (timezone-independent). */
export function formatUtcRelativeAgo(utcIso: string): string {
    const date = parseUtcInstant(utcIso);
    if (!date) {
        return 'unknown';
    }

    const seconds = Math.round((Date.now() - date.getTime()) / 1000);
    if (seconds < 45) {
        return 'just now';
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min ago`;
    }
    const hours = Math.round(minutes / 60);
    if (hours < 48) {
        return `${hours} hr ago`;
    }
    const days = Math.round(hours / 24);
    if (days < 14) {
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }

    return formatUtcInstantInTimezone(utcIso, {
        timezone: 'UTC',
        date_format: 'dd/mm/yyyy',
        time_format: '24h',
    });
}

export function useDateTimeFormat() {
    const page = usePage();

    const dateTimeFormat = computed(
        () => (page.props.dateTimeFormat as DateTimeFormatProps | undefined),
    );

    const resolved = computed(() => buildDateTimeOptions(dateTimeFormat.value));

    function normalizedFormat(): DateTimeFormatProps {
        return {
            timezone: dateTimeFormat.value?.timezone || 'UTC',
            date_format: dateTimeFormat.value?.date_format || 'dd/mm/yyyy',
            time_format: dateTimeFormat.value?.time_format || '24h',
        };
    }

    function formatDate(value: string | Date | null | undefined): string {
        return formatLocalizedDate(value, normalizedFormat());
    }

    function formatTime(value: string | Date | null | undefined): string {
        return formatLocalizedTime(value, normalizedFormat());
    }

    function formatDateTime(value: string | Date | null | undefined): string {
        return formatLocalizedDateTime(value, normalizedFormat());
    }

    return {
        dateTimeFormat,
        formatDate,
        formatTime,
        formatDateTime,
    };
}
