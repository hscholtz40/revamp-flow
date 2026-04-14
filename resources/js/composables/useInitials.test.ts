import { describe, expect, it } from 'vitest';
import { getInitials, useInitials } from './useInitials';

describe('useInitials', () => {
    it('builds initials from full names', () => {
        expect(getInitials('Ada Lovelace')).toBe('AL');
        expect(getInitials('single')).toBe('S');
        expect(getInitials('  mary jane watson  ')).toBe('MW');
    });

    it('returns empty initials for missing names', () => {
        expect(getInitials()).toBe('');
        expect(useInitials().getInitials('')).toBe('');
    });
});
