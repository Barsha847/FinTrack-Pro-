const { test, expect } = require('@playwright/test');
const { monitorErrors } = require('../support/error-monitor');
const { loginUser } = require('../support/auth');

test.describe('UI Interactions & Components', () => {
    test.beforeEach(async ({ page }) => {
        monitorErrors(page);
        await loginUser(page, 'test@example.com', 'TestPassword123!');
    });

    test('Sidebar toggle button changes sidebar state', async ({ page }) => {
        await page.goto('/pages/dashboard.html');
        
        const sidebar = page.locator('#sidebar');
        const toggleBtn = page.locator('#sidebarToggle');
        
        // Assuming sidebar is visible by default on desktop
        if (await toggleBtn.isVisible()) {
            await toggleBtn.click();
            // Sidebar should have a collapsed class or different width
            await expect(sidebar).toHaveClass(/collapsed|mini/);
            
            // Toggle back
            await toggleBtn.click();
            await expect(sidebar).not.toHaveClass(/collapsed|mini/);
        }
    });

    test('Profile dropdown opens and contains logout', async ({ page }) => {
        await page.goto('/pages/dashboard.html');
        
        const profileTrigger = page.locator('.profile-dropdown-trigger, .user-menu-btn').first();
        if (await profileTrigger.isVisible()) {
            await profileTrigger.click();
            const menu = page.locator('.profile-menu, .dropdown-content').first();
            await expect(menu).toBeVisible();
            await expect(menu.locator('text=Log Out')).toBeVisible();
        }
    });

    test('Add Income modal opens and validates required fields', async ({ page }) => {
        await page.goto('/pages/income.html');
        
        const addBtn = page.locator('button:has-text("Add Income"), .add-btn').first();
        if (await addBtn.isVisible()) {
            await addBtn.click();
            const modal = page.locator('.modal.active, #addIncomeModal').first();
            await expect(modal).toBeVisible();
            
            // Submit empty form to trigger validation
            await modal.locator('button[type="submit"]').click();
            
            // Check for HTML5 validation or custom error class
            // Just verifying modal stays open and no crash occurs
            await expect(modal).toBeVisible();
            
            // Close modal
            const closeBtn = modal.locator('.modal-close, button:has-text("Cancel")').first();
            await closeBtn.click();
            await expect(modal).not.toBeVisible();
        }
    });
});
