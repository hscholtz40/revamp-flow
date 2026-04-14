import { beforeEach, describe, expect, it } from 'vitest';
import { useRecentlyViewed } from './useRecentlyViewed';

describe('useRecentlyViewed', () => {
    beforeEach(() => {
        localStorage.clear();
        useRecentlyViewed().clearAll();
    });

    it('adds tracked items and preserves friendly titles', () => {
        const recentlyViewed = useRecentlyViewed();

        recentlyViewed.addItem('/customers/42');

        expect(recentlyViewed.items.value).toHaveLength(1);
        expect(recentlyViewed.items.value[0]?.title).toBe('Customer #42');
    });

    it('ignores excluded auth routes and de-duplicates by clean url', () => {
        const recentlyViewed = useRecentlyViewed();

        recentlyViewed.addItem('/login');
        recentlyViewed.addItem('/quotes/10?tab=details');
        recentlyViewed.addItem('/quotes/10?tab=history');

        expect(recentlyViewed.items.value).toHaveLength(1);
        expect(recentlyViewed.items.value[0]?.url).toBe('/quotes/10?tab=history');
    });
});
