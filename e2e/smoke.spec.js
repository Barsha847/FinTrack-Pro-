const { test, expect } = require('@playwright/test');

test('basic smoke test - login page loads', async ({ page }) => {
  // Assuming the development server redirects unauthenticated roots to /pages/login.html
  // or that the login page itself is directly accessible.
  
  await page.goto('/pages/login.html');
  
  // Verify the page actually loaded by checking the title or a known element
  // Since we don't have the exact DOM, we expect a 200/300 status and the body to exist.
  await expect(page).toHaveTitle(/FinTrack Pro/i, { timeout: 10000 });
  
  // Additional basic assertions could go here for Phase 2
});
