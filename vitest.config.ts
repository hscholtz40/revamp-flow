import viteConfig from './vite.config';
import { defineConfig, mergeConfig } from 'vitest/config';

export default mergeConfig(
    viteConfig,
    defineConfig({
        test: {
            environment: 'jsdom',
            globals: true,
            include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
            coverage: {
                provider: 'v8',
                reportsDirectory: 'storage/coverage/frontend',
                reporter: ['text', 'json-summary', 'html', 'lcov'],
                include: ['resources/js/**/*.{ts,tsx,vue}'],
                exclude: [
                    'resources/js/**/*.d.ts',
                    'resources/js/**/*.{test,spec}.{ts,tsx}',
                    'resources/js/routes/**',
                ],
                thresholds: {
                    branches: 0.5,
                    functions: 0.5,
                    lines: 1,
                    statements: 1,
                },
            },
        },
    }),
);
