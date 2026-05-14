// @ts-check
import { defineConfig, devices } from '@playwright/test';

// BASE_URL=http://127.0.0.1:8100 npx playwright test  →  run against staging
// BASE_URL unset                                        →  auto-start local dev server
const baseURL = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const localDev = !process.env.BASE_URL;

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 2 : 0,
    workers: 1,
    reporter: [['line'], ['html', { open: 'never' }]],
    timeout: 30_000,

    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    webServer: localDev ? {
        command: 'php artisan serve',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
        timeout: 15_000,
    } : undefined,
});
