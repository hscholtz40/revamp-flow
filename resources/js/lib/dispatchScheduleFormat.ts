/**
 * Parse Inertia/API schedule values that may be ISO strings or hydrated Date instances.
 */
export function parseScheduleInstant(value: unknown): Date | null {
    if (value == null || value === '') return null;
    if (value instanceof Date) {
        return Number.isNaN(value.getTime()) ? null : value;
    }
    const d = new Date(value as string);
    return Number.isNaN(d.getTime()) ? null : d;
}

/**
 * Local wall time for display, e.g. Mon Apr 20 2026 13:15:00 — no GMT offset or long timezone name.
 */
export function formatDispatchScheduleInstant(value: unknown): string {
    const d = parseScheduleInstant(value);
    if (!d) return '—';
    const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');
    return `${weekdays[d.getDay()]} ${months[d.getMonth()]} ${d.getDate()} ${d.getFullYear()} ${hh}:${mm}:${ss}`;
}
