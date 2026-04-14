import { describe, expect, it } from 'vitest';
import { getSafeExternalUrl } from './useSafeExternalUrl';

describe('getSafeExternalUrl', () => {
    it('allows safe external protocols', () => {
        expect(getSafeExternalUrl('https://example.com/docs')).toBe('https://example.com/docs');
        expect(getSafeExternalUrl('mailto:test@example.com')).toBe('mailto:test@example.com');
        expect(getSafeExternalUrl('tel:+27123456789')).toBe('tel:+27123456789');
    });

    it('rejects unsafe or empty values', () => {
        expect(getSafeExternalUrl('javascript:alert(1)')).toBeNull();
        expect(getSafeExternalUrl('data:text/html;base64,abc')).toBeNull();
        expect(getSafeExternalUrl('')).toBeNull();
        expect(getSafeExternalUrl(null)).toBeNull();
    });
});
