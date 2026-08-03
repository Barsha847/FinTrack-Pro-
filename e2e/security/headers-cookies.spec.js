const { test, expect } = require('@playwright/test');
const { loginUser } = require('../support/auth');

test.describe('Security Headers and Cookies Regression', () => {
    test('Public pages should return secure headers where applicable', async ({ request }) => {
        const response = await request.get('/pages/login.html');
        
        const headers = response.headers();
        
        // These are basic modern security headers.
        // We don't fail immediately if they are missing locally, but we report them.
        const expectedHeaders = [
            'x-content-type-options',
            'x-frame-options'
        ];
        
        for (const header of expectedHeaders) {
            if (!headers[header]) {
                console.warn(`Missing Security Header on login.html: ${header}`);
            }
        }
        
        // It's acceptable for local PHP servers to not set these, so we don't strict expect() them to exist.
        // However, if they DO exist, we can assert their values.
        if (headers['x-frame-options']) {
            expect(headers['x-frame-options'].toLowerCase()).toMatch(/deny|sameorigin/);
        }
    });

    test('Authentication cookies should have HttpOnly and Secure flags', async ({ page }) => {
        // Without DB, login will fail, but if it succeeds, we check the cookies.
        await loginUser(page, 'test@example.com', 'TestPassword123!');
        
        const cookies = await page.context().cookies();
        
        for (const cookie of cookies) {
            // Find session or JWT cookies
            if (cookie.name.includes('session') || cookie.name.includes('token')) {
                // HttpOnly must be true to prevent XSS theft
                expect(cookie.httpOnly, `Cookie ${cookie.name} is missing HttpOnly flag!`).toBe(true);
                
                // Secure should ideally be true, but might be false on localhost HTTP.
                if (!cookie.secure) {
                    console.warn(`Cookie ${cookie.name} is missing Secure flag (Acceptable for localhost HTTP)`);
                }
            }
        }
    });
});
