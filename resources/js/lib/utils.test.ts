import { describe, expect, it } from 'vitest';
import { urlIsActive } from '@/lib/utils';

const navHrefs = [
    '/client-zone',
    '/client-zone/documents',
    '/client-zone/request-update',
    '/invoices',
    '/quotes',
    '/customers',
];

describe('urlIsActive', () => {
    it('matches exact list routes', () => {
        expect(urlIsActive('/invoices', '/invoices', navHrefs)).toBe(true);
    });

    it('matches nested record routes for module index links', () => {
        expect(urlIsActive('/invoices', '/invoices/42', navHrefs)).toBe(true);
        expect(urlIsActive('/invoices', '/invoices/42/edit', navHrefs)).toBe(true);
        expect(urlIsActive('/quotes', '/quotes/9/show', navHrefs)).toBe(true);
    });

    it('prefers more specific client zone nav items', () => {
        expect(urlIsActive('/client-zone/documents', '/client-zone/documents', navHrefs)).toBe(true);
        expect(urlIsActive('/client-zone', '/client-zone/documents', navHrefs)).toBe(false);
    });

    it('does not match sibling module paths', () => {
        expect(urlIsActive('/invoices', '/quotes/42', navHrefs)).toBe(false);
        expect(urlIsActive('/customers', '/customers-old', navHrefs)).toBe(false);
    });
});
