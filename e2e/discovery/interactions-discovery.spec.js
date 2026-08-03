const { test, expect } = require('@playwright/test');
const manifest = require('../fixtures/application-manifest');
const { loginUser } = require('../support/auth');

test.describe('Interactive Elements Auto-Discovery', () => {
    test('All critical buttons and forms on major pages must be classified', async ({ page }) => {
        await loginUser(page, 'test@example.com', 'TestPassword123!');

        // Check a representative sample of pages instead of crawling all to avoid slowness
        const pagesToCheck = ['/pages/dashboard.html', '/pages/income.html'];

        for (const pageUrl of pagesToCheck) {
            await page.goto(pageUrl, { waitUntil: 'domcontentloaded' });
            
            // Find all buttons and submit inputs
            const elements = await page.locator('button, input[type="submit"]').evaluateAll(els => {
                return els.map(el => {
                    return {
                        id: el.id ? `#${el.id}` : null,
                        classes: el.className ? `.${el.className.split(' ').join('.')}` : null,
                        type: el.tagName.toLowerCase(),
                        text: el.textContent?.trim() || el.value || 'Unknown'
                    };
                });
            });

            const pageName = pageUrl.split('/').pop();

            // Very simplified check: if a button has a specific ID or primary class, it should be in the manifest.
            // This prevents future critical interactions from remaining untested.
            for (const el of elements) {
                // If it has no ID and no meaningful class, it's probably generic (handled by covered/excluded rules)
                if (!el.id && (!el.classes || el.classes === '.')) continue;

                const selectorIdentifier = el.id || el.classes;
                const specificCheck = `${pageName}:${selectorIdentifier}`;
                const wildcardCheck = `*:${selectorIdentifier}`;

                const isCritical = manifest.interactions.critical.includes(specificCheck) || manifest.interactions.critical.includes(wildcardCheck);
                const isCovered = manifest.interactions.covered.includes(specificCheck) || manifest.interactions.covered.includes(wildcardCheck);
                const isExcluded = manifest.interactions.explicitlyExcluded.some(ex => ex.selector === specificCheck || ex.selector === wildcardCheck);

                const isClassified = isCritical || isCovered || isExcluded;

                // We won't strictly fail the test for generic UI framework buttons that aren't classified yet,
                // but we will flag primary action buttons.
                if (selectorIdentifier && (selectorIdentifier.includes('add-btn') || selectorIdentifier.includes('submit'))) {
                    if (!isClassified) {
                        console.error(`UNCLASSIFIED CRITICAL ELEMENT on ${pageName}: ${selectorIdentifier} ("${el.text}")`);
                    }
                    expect(isClassified, `Unclassified interaction found: ${selectorIdentifier} on ${pageName}. Add to application-manifest.js`).toBeTruthy();
                }
            }
        }
    });
});
