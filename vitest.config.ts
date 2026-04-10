import viteConfig from './vite.config';
import { defineConfig, mergeConfig } from 'vitest/config';

export default mergeConfig(
    viteConfig,
    defineConfig({
        test: {
            environment: 'jsdom',
            globals: true,
            include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        },
    }),
);
