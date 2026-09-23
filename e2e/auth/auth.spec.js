const { test, expect } = require('@playwright/test');
const { monitorErrors } = require('../support/error-monitor');
const { loginUser } = require('../support/auth');

test.describe('Authentication Regression', () => {
    test.beforeEach(async ({ page }) => {
        monitorErrors(page);
    });

    test('valid user can login and see dashboard', async ({ page }) => {
        await page.goto('/pages/login.html', { waitUntil: 'load' });
        await page.fill('#loginEmail, #email, input[name="email"]', 'test@example.com');
        await page.fill('#loginPassword, #password, input[name="password"]', 'TestPassword123!');
        await page.click('button[type="submit"]');

        await page.waitForURL(/\/pages\/dashboard\.html/, { timeout: 5000 });
        await expect(page.locator('.dashboard-layout')).toBeVisible();
    });

    test('invalid user sees error message', async ({ page }) => {
        await page.goto('/pages/login.html', { waitUntil: 'load' });
        await page.fill('#loginEmail, #email, input[name="email"]', 'wrong@example.com');
        await page.fill('#loginPassword, #password, input[name="password"]', 'Wrong123!');
        await page.click('button[type="submit"]');

        // Should not navigate to dashboard
        await page.waitForTimeout(1000);
        expect(page.url()).not.toContain('dashboard.html');
    });

    test('unauthenticated users are redirected to login from protected routes', async ({ page }) => {
        // Direct URL authorization test
        const protectedRoutes = [
            '/pages/dashboard.html',
            '/pages/income.html',
            '/pages/expenses.html',
            '/pages/budgets.html',
            '/pages/savings.html',
            '/pages/investments.html',
            '/pages/loans.html',
            '/pages/bills.html',
            '/pages/reports.html',
            '/pages/analytics.html',
            '/pages/settings.html'
        ];

        for (const route of protectedRoutes) {
            await page.goto(route);
            // Should be redirected back to login
            await page.waitForURL(/\/pages\/login\.html/, { timeout: 3000 });
            expect(page.url()).toContain('login.html');
        }
    });

    test('unauthenticated users get 401 from protected APIs', async ({ request }) => {
        const response = await request.get('/api/auth/me', {
            headers: {
                'Accept': 'application/json'
            }
        });
        
        expect(response.status()).toBe(401);
    });

    test('logout successfully terminates session and prevents Back navigation', async ({ page, context }) => {
        await loginUser(page, 'test@example.com', 'TestPassword123!');
        await page.goto('/pages/dashboard.html');

        // Click real logout UI
        const logoutBtn = page.locator('#logoutBtn, a[href*="logout"], button:has-text("Log out")').first();
        if (await logoutBtn.isVisible()) {
            await logoutBtn.click();
        } else {
            // Alternative generic locator
            await page.locator('text=Log Out').first().click();
        }

        // Verify redirect to login
        await page.waitForURL(/\/pages\/login\.html/, { timeout: 5000 });

        // IMPORTANT BROWSER BACK PROTECTION TEST
        await page.goBack();
        
        // Wait briefly to see if it redirects again or allows access
        await page.waitForTimeout(1000);
        
        // The URL should still be login, or if it says dashboard it should instantly redirect or be unusable.
        // We assert that the user cannot interact with protected data.
        const title = await page.title();
        if (page.url().includes('dashboard.html')) {
            // If the URL goes back but it triggers a JS redirect, wait for it
            await page.waitForURL(/\/pages\/login\.html/, { timeout: 3000 }).catch(() => {});
            
            // If it's still on dashboard, it's a security cache bug. Let's assert it shouldn't be usable.
            // A strict assertion: it must redirect back to login.
            expect(page.url()).toContain('login.html');
        } else {
            expect(page.url()).toContain('login.html');
        }
    });

    test('browser forward after logout is protected', async ({ page }) => {
        await loginUser(page, 'test@example.com', 'TestPassword123!');
        await page.goto('/pages/dashboard.html');
        await page.goto('/pages/profile.html');
        await page.goBack(); // Back to dashboard
        
        // Logout
        await page.locator('text=Log Out').first().click();
        await page.waitForURL(/\/pages\/login\.html/, { timeout: 5000 });

        // Try to go forward to profile
        await page.goForward();
        await page.waitForTimeout(1000);
        
        // Must bounce back to login
        if (page.url().includes('profile.html')) {
            await page.waitForURL(/\/pages\/login\.html/, { timeout: 3000 }).catch(() => {});
        }
        expect(page.url()).toContain('login.html');
    });
});
