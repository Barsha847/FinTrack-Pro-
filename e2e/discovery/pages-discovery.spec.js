const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const manifest = require('../fixtures/application-manifest');

test.describe('Pages Auto-Discovery', () => {
    test('All physical HTML files in pages/ must be registered in the manifest', () => {
        const pagesDir = path.join(__dirname, '../../pages');
        const files = fs.readdirSync(pagesDir).filter(f => f.endsWith('.html'));

        const allRegisteredPages = [
            ...manifest.publicPages,
            ...manifest.protectedPages,
            ...manifest.excludedPages.map(p => p.file)
        ];

        for (const file of files) {
            const isRegistered = allRegisteredPages.includes(file);
            if (!isRegistered) {
                console.error(`UNREGISTERED APPLICATION PAGE: pages/${file}`);
            }
            expect(isRegistered, `Found unregistered page: pages/${file}. You must add it to e2e/fixtures/application-manifest.js`).toBeTruthy();
        }
    });
});
