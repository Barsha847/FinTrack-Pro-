const { test, expect } = require('@playwright/test');
const { monitorErrors } = require('../support/error-monitor');

test.describe('Public Pages Smoke Test', () => {
    test.beforeEach(async ({ page }) => {
        monitorErrors(page);
    });

    const publicPages = [
        { url: '/pages/login.html', title: /Login|FinTrack Pro/i },
        { url: '/pages/signup.html', title: /Sign Up|Register/i },
        { url: '/pages/forgot-password.html', title: /Forgot Password/i },
        { url: '/pages/reset-password.html', title: /Reset Password/i }
    ];

    for (const p of publicPages) {
        test(`should load ${p.url} successfully`, async ({ page }) => {
            const response = await page.goto(p.url, { waitUntil: 'domcontentloaded' });
            
            // Check HTTP status isn't an error
            expect(response.status()).toBeLessThan(400);
            
            // Verify page has loaded with something meaningful
            await expect(page).toHaveTitle(p.title);
        });
    }

    test('should return 404 for non-existent page', async ({ page }) => {
        const response = await page.goto('/pages/does-not-exist.html');
        // Because of router.php, it might return 404 HTTP status
        expect(response.status()).toBe(404);
        // It might load a 404.html page visually too
    });
});
