const { test, expect } = require('@playwright/test');
const manifest = require('../fixtures/application-manifest');
const { loginUser } = require('../support/auth');
const { monitorErrors } = require('../support/error-monitor');

test.describe('Sidebar Auto-Discovery', () => {
    test.beforeEach(async ({ page }) => {
        // We do not want missing hrefs to fail here, but unexpected JS/500 errors should fail
        monitorErrors(page, { ignore404s: true });
    });

    test('All rendered sidebar links must be tracked in the manifest', async ({ page }) => {
        // DB dependency: Requires user login to see sidebar properly
        await loginUser(page, 'test@example.com', 'TestPassword123!');
        
        await page.goto('/pages/dashboard.html');
        
        // Wait for sidebar
        await expect(page.locator('.sidebar-nav')).toBeVisible();

        const links = await page.locator('.sidebar-nav a').evaluateAll(els => {
            return els.map(el => {
                const href = el.getAttribute('href') || '';
                return href.split('/').pop(); // e.g. "dashboard.html"
            }).filter(href => href && !href.startsWith('#') && !href.includes('javascript:'));
        });

        // Ensure we actually found links (so we don't accidentally pass an empty test)
        expect(links.length).toBeGreaterThan(0);

        for (const link of links) {
            const isRegistered = manifest.sidebarRoutes.includes(link);
            if (!isRegistered) {
                console.error(`UNREGISTERED SIDEBAR LINK: ${link}`);
            }
            expect(isRegistered, `Found unregistered sidebar link pointing to: ${link}. Add to application-manifest.js`).toBeTruthy();
        }
    });
});
