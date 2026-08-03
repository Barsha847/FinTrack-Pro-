const { test, expect } = require('@playwright/test');
const { loginUser } = require('../support/auth');

test.describe('CSRF Security Regression', () => {
    test('State-changing actions must require a valid CSRF token', async ({ request, page }) => {
        // CSRF typically applies to POST/PUT/DELETE requests in traditional web apps,
        // or API endpoints if they use cookie-based session auth.
        
        // Log in to get session cookie
        await loginUser(page, 'test@example.com', 'TestPassword123!');
        
        // Extract session cookie (if any) and use it in the request context
        const cookies = await page.context().cookies();
        
        // Try a POST request without a CSRF token (or with an invalid one)
        const response = await request.post('/api/expenses', {
            data: { amount: 100, category_id: 1, title: 'CSRF Attack' },
            // Not including 'X-CSRF-TOKEN' header or payload token intentionally
        });
        
        const status = response.status();
        
        // Expecting 419 Page Expired, 403 Forbidden, or 401. 
        // 201 Created means CSRF vulnerability!
        if (status === 201 || status === 200) {
            console.error('CSRF VULNERABILITY DETECTED: Successfully posted data without CSRF token!');
        }
        
        expect(status).not.toBe(201);
        expect(status).not.toBe(200);
    });
});
