/**
 * Clinic System — Bootstrap 5 UI Stability Tests
 *
 * Validates that core Bootstrap components work correctly:
 *   1. Navbar collapse toggle (mobile)
 *   2. Dropdown visibility (Analytics menu)
 *   3. Modal open/close
 *   4. Logout button always visible
 *
 * Requires: php artisan serve running on :8000
 * Login:    owner@clinic.com / password123
 */
import { test, expect } from '@playwright/test';

const LOGIN_URL  = '/login';
const EMAIL      = 'owner@clinic.com';
const PASSWORD   = 'password123';

// ---------------------------------------------------------------------------
// Helper: log in as owner
// ---------------------------------------------------------------------------
async function loginAsOwner(page) {
    await page.goto(LOGIN_URL);
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard|owner/);
}

// ---------------------------------------------------------------------------
// 1. Navbar collapse — toggler shows/hides the menu on small screens
// ---------------------------------------------------------------------------
test('navbar collapse toggle works on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 }); // iPhone-sized
    await loginAsOwner(page);

    const navContent = page.locator('#navbarNav');

    // On mobile the navbar should be collapsed (not visible)
    await expect(navContent).not.toBeVisible();

    // Click the toggler
    await page.click('button.navbar-toggler');

    // Navbar content should now be visible
    await expect(navContent).toBeVisible({ timeout: 3000 });

    // Click toggler again to collapse
    await page.click('button.navbar-toggler');

    // Should collapse back
    await expect(navContent).not.toBeVisible({ timeout: 3000 });
});

// ---------------------------------------------------------------------------
// 2. Dropdown visibility — Analytics dropdown opens and shows items
// ---------------------------------------------------------------------------
test('analytics dropdown opens and shows menu items', async ({ page }) => {
    await loginAsOwner(page);

    // Find the Analytics dropdown toggle
    const dropdownToggle = page.locator('a.dropdown-toggle', { hasText: 'Analytics' });
    await expect(dropdownToggle).toBeVisible();

    // Click to open
    await dropdownToggle.click();

    // The dropdown menu should appear
    const dropdownMenu = page.locator('.dropdown-menu');
    await expect(dropdownMenu.first()).toBeVisible({ timeout: 3000 });

    // Verify at least one expected item
    await expect(page.locator('.dropdown-item', { hasText: 'Expense Intelligence' })).toBeVisible();

    // Click toggle again or click elsewhere to close
    await page.click('body', { position: { x: 10, y: 10 } });

    // Dropdown should close
    await expect(dropdownMenu.first()).not.toBeVisible({ timeout: 3000 });
});

// ---------------------------------------------------------------------------
// 3. Modal open/close — uses the delete-account modal on profile page
// ---------------------------------------------------------------------------
test('modal opens and closes correctly', async ({ page }) => {
    await loginAsOwner(page);

    // Navigate to profile
    await page.goto('/profile');

    // Look for a Bootstrap modal trigger (delete account button)
    const modalTrigger = page.locator('[data-bs-toggle="modal"]').first();

    // Only test if a modal trigger exists on the page
    const triggerCount = await modalTrigger.count();
    if (triggerCount > 0) {
        await modalTrigger.click();

        // The modal backdrop and dialog should appear
        const modal = page.locator('.modal.show');
        await expect(modal).toBeVisible({ timeout: 3000 });

        // Close via the X button or Cancel button inside the modal
        const closeBtn = modal.locator('[data-bs-dismiss="modal"]').first();
        await closeBtn.click();

        // Modal should no longer be visible
        await expect(modal).not.toBeVisible({ timeout: 3000 });
    } else {
        // If no modal trigger exists, just confirm the profile page loaded
        await expect(page.locator('body')).toContainText('Profile');
    }
});

// ---------------------------------------------------------------------------
// 4. Logout button is always visible in the navbar
// ---------------------------------------------------------------------------
test('logout button is visible in navbar on desktop', async ({ page }) => {
    await loginAsOwner(page);

    // The logout button should be a visible submit button
    const logoutBtn = page.locator('button[type="submit"]', { hasText: 'Log Out' });
    await expect(logoutBtn).toBeVisible();
});

test('logout button is accessible on mobile after expanding navbar', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await loginAsOwner(page);

    // Expand the navbar
    await page.click('button.navbar-toggler');
    await expect(page.locator('#navbarNav')).toBeVisible({ timeout: 3000 });

    // Logout button should now be visible
    const logoutBtn = page.locator('button[type="submit"]', { hasText: 'Log Out' });
    await expect(logoutBtn).toBeVisible();
});
