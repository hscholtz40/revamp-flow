import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export interface NumberFormatProps {
    decimal_separator: string;
    thousands_separator: string;
}

/**
 * Group integer part of a digit string with thousands separators (ASCII digits only).
 */
function addThousandsGroups(intDigits: string, thousandsSep: string): string {
    if (!thousandsSep) {
        return intDigits;
    }
    return intDigits.replace(/\B(?=(\d{3})+(?!\d))/g, thousandsSep);
}

/**
 * Format a number with min/max fraction digits using company separators (no currency symbol).
 */
export function formatDecimalWithSeparators(
    amount: number,
    minFractionDigits: number,
    maxFractionDigits: number,
    decimalSep: string,
    thousandsSep: string,
): string {
    const negative = amount < 0;
    const n = Math.abs(amount);
    const fixed = n.toFixed(maxFractionDigits);
    const [intPart, fracPart = ''] = fixed.split('.');

    let frac = fracPart;
    if (maxFractionDigits > 0) {
        frac = frac.replace(/0+$/, '');
        while (frac.length < minFractionDigits) {
            frac += '0';
        }
    }

    const grouped = addThousandsGroups(intPart, thousandsSep);
    const num =
        maxFractionDigits === 0 || frac.length === 0
            ? grouped
            : `${grouped}${decimalSep}${frac}`;

    return negative ? `-${num}` : num;
}

export function useNumberFormat() {
    const page = usePage();

    const decimalSeparator = computed(
        () => (page.props.numberFormat as NumberFormatProps | undefined)?.decimal_separator ?? '.',
    );
    const thousandsSeparator = computed(
        () => (page.props.numberFormat as NumberFormatProps | undefined)?.thousands_separator ?? ',',
    );

    function formatDecimal(amount: number, minFd = 0, maxFd = 2): string {
        return formatDecimalWithSeparators(
            amount,
            minFd,
            maxFd,
            decimalSeparator.value,
            thousandsSeparator.value,
        );
    }

    function formatCurrency(amount: number, currencyCode = 'ZAR'): string {
        const code = (currencyCode || 'ZAR').toUpperCase();
        if (code === 'ZAR') {
            return 'R' + formatDecimal(amount, 2, 2);
        }
        return new Intl.NumberFormat('en-ZA', { style: 'currency', currency: code }).format(amount ?? 0);
    }

    return {
        decimalSeparator,
        thousandsSeparator,
        formatDecimal,
        formatCurrency,
    };
}
