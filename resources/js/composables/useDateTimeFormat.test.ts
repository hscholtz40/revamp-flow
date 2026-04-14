import { describe, expect, it } from 'vitest';
import {
    buildDateTimeOptions,
    formatLocalizedDate,
    formatLocalizedDateTime,
    formatLocalizedTime,
} from './useDateTimeFormat';

describe('useDateTimeFormat helpers', () => {
    const format = {
        timezone: 'UTC',
        date_format: 'dd/mm/yyyy' as const,
        time_format: '24h' as const,
    };

    it('builds locale and timezone options from the company format', () => {
        expect(buildDateTimeOptions(format)).toEqual({
            locale: 'en-GB',
            timezone: 'UTC',
            hour12: false,
        });
    });

    it('formats localized date, time, and datetime values', () => {
        const value = '2024-05-01T15:30:00Z';

        expect(formatLocalizedDate(value, format)).toBe('01/05/2024');
        expect(formatLocalizedTime(value, format)).toContain('15:30');
        expect(formatLocalizedDateTime(value, format)).toContain('01/05/2024');
    });

    it('returns fallback text for invalid values', () => {
        expect(formatLocalizedDate('not-a-date', format)).toBe('N/A');
        expect(formatLocalizedTime(null, format)).toBe('N/A');
        expect(formatLocalizedDateTime(undefined, format)).toBe('N/A');
    });
});
