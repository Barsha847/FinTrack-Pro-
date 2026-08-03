const { test, expect } = require('@playwright/test');
const { monitorErrors } = require('../support/error-monitor');
const { loginUser } = require('../support/auth');

test.describe('Sidebar Navigation (Protected)', () => {
    // We will test if sidebar links navigate successfully without 404/500s.
    // If the database is missing, login will fail, and this test will fail, which is expected in a DB-less local run.
    
    test.beforeEach(async ({ page }) => {
        monitorErrors(page);
        // Assuming test user credentials that would be seeded in CI
        await loginUser(page, 'test@example.com', 'TestPassword123!');
    });

    const sidebarLinks = [
        { name: 'Dashboard', url: '/pages/dashboard.html' },
        { name: 'Income', url: '/pages/income.html' },
        { name: 'Expenses', url: '/pages/expenses.html' },
        { name: 'Budgets', url: '/pages/budgets.html' },
        { name: 'Savings', url: '/pages/savings.html' },
        { name: 'Investments', url: '/pages/investments.html' },
        { name: 'Loans', url: '/pages/loans.html' },
        { name: 'Bills', url: '/pages/bills.html' },
        { name: 'Reports', url: '/pages/reports.html' },
        { name: 'Analytics', url: '/pages/analytics.html' },
        { name: 'Settings', url: '/pages/settings.html' }
    ];

    for (const link of sidebarLinks) {
        test(`should navigate to ${link.name} successfully`, async ({ page }) => {
            // First ensure we are on a page with a sidebar (e.g. dashboard)
            await page.goto('/pages/dashboard.html');
            
            // Find the sidebar link by matching href or text
            const navLink = page.locator(`.sidebar-nav a[href*="${link.url.split('/').pop()}"]`).first();
            
            // If the element doesn't exist in the sidebar for some reason, we fail gracefully
            await expect(navLink).toBeVisible();
            
            // Click it like a user
            await navLink.click();
            
            // Verify navigation
            await page.waitForURL(new RegExp(link.url.split('/').pop()), { timeout: 5000 });
            
            // Verify HTTP 500/404 hasn't occurred (handled by error-monitor)
            // Verify content is loaded (basic check for standard FinTrack header)
            const title = await page.title();
            expect(title).not.toMatch(/404|Error/i);
        });
    }
});
