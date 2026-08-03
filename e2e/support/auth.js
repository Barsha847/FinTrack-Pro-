const { expect } = require('@playwright/test');

/**
 * Standard login helper to be used across tests that require authentication.
 */
async function loginUser(page, email, password) {
    await page.goto('/pages/login.html', { waitUntil: 'domcontentloaded' });
    await page.fill('#loginEmail, #email, input[name="email"]', email);
    await page.fill('#loginPassword, #password, input[name="password"]', password);
    await page.click('button[type="submit"]');
    
    // Wait for either the dashboard to load or an error message
    // If it navigates to dashboard, it was successful.
    await page.waitForURL(/\/pages\/dashboard\.html/, { timeout: 5000 }).catch(() => {});
}

module.exports = { loginUser };
