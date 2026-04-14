import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const page = reactive({
    props: {
        numberFormat: {
            decimal_separator: '.',
            thousands_separator: ',',
        },
    },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
}));

import { formatDecimalWithSeparators, useNumberFormat } from './useNumberFormat';

describe('useNumberFormat', () => {
    beforeEach(() => {
        page.props.numberFormat = {
            decimal_separator: '.',
            thousands_separator: ',',
        };
    });

    it('formats decimals with custom separators', () => {
        expect(formatDecimalWithSeparators(12345.6, 2, 2, ',', ' ')).toBe('12 345,60');
        expect(formatDecimalWithSeparators(-50, 0, 0, '.', ',')).toBe('-50');
    });

    it('formats decimal and currency values from page props', () => {
        const formatter = useNumberFormat();

        expect(formatter.formatDecimal(1234.5, 2, 2)).toBe('1,234.50');
        expect(formatter.formatCurrency(1234.5, 'ZAR')).toBe('R1,234.50');
    });

    it('reacts to company separator changes', () => {
        const formatter = useNumberFormat();

        page.props.numberFormat = {
            decimal_separator: ',',
            thousands_separator: ' ',
        };

        expect(formatter.formatDecimal(9876.5, 1, 2)).toBe('9 876,5');
    });
});
