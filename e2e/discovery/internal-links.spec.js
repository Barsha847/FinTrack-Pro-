const { test, expect } = require('@playwright/test');
const { loginUser } = require('../support/auth');
const { monitorErrors } = require('../support/error-monitor');

test.describe('Internal Link Crawler', () => {
    test.beforeEach(async ({ page }) => {
        // We will catch 404s natively with monitorErrors!
        // But for crawling, we might encounter some expected dead links if features aren't built.
        // The instruction says "Do not automatically label every one as vulnerable. Report suspicious usage."
        // We will enable strict 404 monitoring.
        monitorErrors(page, { ignore404s: false });
    });

    test('Crawl public pages for dead internal links', async ({ page }) => {
        const publicPages = ['/pages/login.html', '/pages/signup.html'];
        
        for (const startPage of publicPages) {
            await page.goto(startPage, { waitUntil: 'domcontentloaded' });
            
            const links = await page.locator('a').evaluateAll(els => els.map(el => el.getAttribute('href')).filter(Boolean));
            
            for (const href of links) {
                // Ignore external, mailto, tel, empty anchors, or javascript
                if (href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:') || href === '#' || href.startsWith('javascript:')) {
                    continue;
                }
                
                // Construct full local URL and probe it
                // We don't click it to avoid leaving the page, we just make a fetch request.
                const response = await page.request.get(new URL(href, page.url()).href);
                expect(response.status(), `Dead link found: ${href} on ${startPage}`).toBeLessThan(400);
            }
        }
    });

    test('Crawl dashboard for dead internal links', async ({ page }) => {
        await loginUser(page, 'test@example.com', 'TestPassword123!');
        await page.goto('/pages/dashboard.html', { waitUntil: 'domcontentloaded' });
        
        const links = await page.locator('a').evaluateAll(els => els.map(el => el.getAttribute('href')).filter(Boolean));
        
        for (const href of links) {
            if (href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:') || href === '#' || href.startsWith('javascript:')) {
                continue;
            }
            
            const response = await page.request.get(new URL(href, page.url()).href);
            // 401/403 is fine (protected API), but 404/500 is a dead link
            if (response.status() === 404 || response.status() >= 500) {
                console.error(`Suspicious dead internal link: ${href} on dashboard`);
            }
            expect([404, 500, 502, 503]).not.toContain(response.status());
        }
    });
});
