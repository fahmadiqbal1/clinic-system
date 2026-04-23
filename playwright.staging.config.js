// @ts-check
import { defineConfig, devices } from '@playwright/test';

/**
 * Staging-only Playwright config. Points to port 8100.
 * Run: npx playwright test --config=playwright.staging.config.js
 */
export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 1,
    workers: 1,
    reporter: [['line'], ['html', { open: 'never' }]],
    timeout: 30_000,

    use: {
        baseURL: 'http://127.0.0.1:8100',
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
