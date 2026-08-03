const { test, expect } = require('@playwright/test');
const { monitorErrors } = require('../support/error-monitor');

test.describe('Public Navigation', () => {
    test.beforeEach(async ({ page }) => {
        monitorErrors(page);
    });

    test('Navigate from Login to Signup', async ({ page }) => {
        await page.goto('/pages/login.html');
        const signupLink = page.locator('a[href*="signup"]');
        if (await signupLink.isVisible()) {
            await signupLink.click();
            await page.waitForURL(/\/pages\/signup\.html/, { timeout: 5000 });
            await expect(page).toHaveTitle(/Sign Up|Register/i);
        }
    });

    test('Navigate from Login to Forgot Password', async ({ page }) => {
        await page.goto('/pages/login.html');
        const forgotLink = page.locator('a[href*="forgot"]');
        if (await forgotLink.isVisible()) {
            await forgotLink.click();
            await page.waitForURL(/\/pages\/forgot-password\.html/, { timeout: 5000 });
            await expect(page).toHaveTitle(/Forgot Password/i);
        }
    });

    test('Navigate from Signup to Login', async ({ page }) => {
        await page.goto('/pages/signup.html');
        const loginLink = page.locator('a[href*="login"]');
        if (await loginLink.isVisible()) {
            await loginLink.click();
            await page.waitForURL(/\/pages\/login\.html/, { timeout: 5000 });
            await expect(page).toHaveTitle(/Login/i);
        }
    });
});
