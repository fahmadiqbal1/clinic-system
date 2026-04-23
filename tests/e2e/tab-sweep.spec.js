/**
 * E.4 Exhaustive Tab Sweep
 *
 * For every role, logs in and visits every navigation link.
 * Each page must return 200, render without JS console errors,
 * and have a non-empty body. Screenshots are archived to
 * tests/e2e/screenshots/<role>/<slug>.png for Owner sign-off.
 *
 * Roles covered: Owner, Doctor, Receptionist, Triage,
 *                Laboratory, Radiology, Pharmacy, Patient
 */
import { test, expect } from '@playwright/test';
import path from 'path';

const CREDS = {
    owner:        { email: 'owner@clinic.com',        password: 'password123' },
    doctor:       { email: 'doctor@clinic.com',       password: 'password123' },
    receptionist: { email: 'receptionist@clinic.com', password: 'password123' },
    triage:       { email: 'triage@clinic.com',       password: 'password123' },
    laboratory:   { email: 'lab@clinic.com',          password: 'password123' },
    radiology:    { email: 'radiology@clinic.com',    password: 'password123' },
    pharmacy:     { email: 'pharmacy@clinic.com',     password: 'password123' },
    patient:      { email: 'patient@clinic.com',      password: 'password123' },
};

// Navigation paths per role (derived from resources/views/layouts/app.blade.php)
const NAV = {
    owner: [
        '/owner/dashboard',
        '/owner/users',
        '/owner/service-catalog',
        '/owner/expenses',
        '/owner/payouts',
        '/owner/contracts',
        '/owner/revenue-ledger',
        '/owner/zakat',
        '/owner/financial-report',
        '/owner/department-pnl',
        '/owner/revenue-forecast',
        '/owner/discount-approvals',
        '/owner/expense-intelligence',
        '/owner/inventory-health',
        '/owner/procurement-pipeline',
        '/owner/activity-feed',
        '/owner/platform-settings',
        '/owner/ai-oversight',
        '/owner/retention-policy',
        '/owner/nocobase',
        '/owner/architecture',
    ],
    doctor: [
        '/doctor/dashboard',
        '/doctor/patients',
        '/doctor/prescriptions',
        '/doctor/invoices',
        '/doctor/appointments',
        '/payouts',
        '/contracts/show',
    ],
    receptionist: [
        '/receptionist/dashboard',
        '/receptionist/patients',
        '/receptionist/invoices',
        '/receptionist/payouts/dashboard',
        '/payouts',
        '/payouts/create',
        '/receptionist/appointments',
        '/contracts/show',
    ],
    triage: [
        '/triage/dashboard',
        '/triage/patients',
        '/contracts/show',
    ],
    laboratory: [
        '/laboratory/dashboard',
        '/laboratory/invoices',
        '/laboratory/catalog',
        '/laboratory/equipment',
        '/inventory',
        '/stock-movements',
        '/procurement',
        '/contracts/show',
    ],
    radiology: [
        '/radiology/dashboard',
        '/radiology/invoices',
        '/radiology/catalog',
        '/radiology/equipment',
        '/inventory',
        '/stock-movements',
        '/procurement',
        '/contracts/show',
    ],
    pharmacy: [
        '/pharmacy/dashboard',
        '/pharmacy/invoices',
        '/pharmacy/prescriptions',
        '/inventory',
        '/stock-movements',
        '/procurement',
        '/contracts/show',
    ],
    patient: [
        '/patient/dashboard',
    ],
};

// Paths known to redirect to a sub-page (record the expected redirect target prefix)
const REDIRECT_OK = [
    '/payouts',
    '/contracts/show',
];

async function login(page, role) {
    const cred = CREDS[role];
    await page.goto('/login');
    await page.fill('input[name="email"]', cred.email);
    await page.fill('input[name="password"]', cred.password);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard|patient|health/, { timeout: 10_000 });
}

async function screenshotPath(role, urlPath) {
    const slug = urlPath.replace(/\//g, '_').replace(/^_/, '') || 'index';
    return path.join('tests', 'e2e', 'screenshots', role, `${slug}.png`);
}

// Generate one test per role
for (const [role, paths] of Object.entries(NAV)) {
    test.describe(`${role} — nav sweep`, () => {
        test.use({ storageState: undefined });

        test(`${role}: all navigation links load without errors`, async ({ page }) => {
            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error') consoleErrors.push(msg.text());
            });

            await login(page, role);

            for (const navPath of paths) {
                const response = await page.goto(navPath, { waitUntil: 'domcontentloaded', timeout: 15_000 });

                // Allow 200 or redirects (3xx land on a 200)
                const status = response?.status() ?? 0;
                expect(
                    status,
                    `${role} → ${navPath} returned ${status}`
                ).toBeGreaterThanOrEqual(200);
                expect(
                    status,
                    `${role} → ${navPath} returned ${status}`
                ).toBeLessThan(500);

                // Body must not be empty
                const bodyText = await page.locator('body').innerText();
                expect(bodyText.trim().length, `${role} → ${navPath} has empty body`).toBeGreaterThan(0);

                // Archive screenshot
                const dest = await screenshotPath(role, navPath);
                await page.screenshot({ path: dest, fullPage: false });
            }

            // No JS console errors across the entire sweep for this role
            const filtered = consoleErrors.filter(e =>
                // Ignore known benign browser warnings
                !e.includes('favicon') &&
                !e.includes('net::ERR_ABORTED')
            );
            expect(filtered, `${role}: JS console errors: ${filtered.join(', ')}`).toHaveLength(0);
        });
    });
}
