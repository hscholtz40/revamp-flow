import { mount } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initializeTheme, updateTheme, useAppearance } from './useAppearance';

describe('useAppearance', () => {
    const addEventListener = vi.fn();
    const matchMedia = vi.fn().mockReturnValue({
        matches: false,
        addEventListener,
    });

    beforeEach(() => {
        localStorage.clear();
        document.documentElement.className = '';
        document.cookie = '';
        addEventListener.mockClear();
        matchMedia.mockClear();
        vi.stubGlobal('matchMedia', matchMedia);
    });

    it('toggles the dark class directly for explicit themes', () => {
        updateTheme('dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);

        updateTheme('light');
        expect(document.documentElement.classList.contains('dark')).toBe(false);
    });

    it('initializes the theme from storage and listens for system changes', () => {
        localStorage.setItem('appearance', 'dark');

        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
        expect(addEventListener).toHaveBeenCalledWith('change', expect.any(Function));
    });

    it('updates persisted appearance from the composable', async () => {
        let composable!: ReturnType<typeof useAppearance>;

        mount(
            defineComponent({
                setup() {
                    composable = useAppearance();
                    return () => null;
                },
            }),
        );

        composable.updateAppearance('dark');

        expect(composable.appearance.value).toBe('dark');
        expect(localStorage.getItem('appearance')).toBe('dark');
        expect(document.cookie).toContain('appearance=dark');
        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });
});
