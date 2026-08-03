const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const manifest = require('../fixtures/application-manifest');

test.describe('API Route Discovery', () => {
    test('All defined API routes should be documented in the manifest', () => {
        const apiFile = path.join(__dirname, '../../routes/api.php');
        const content = fs.readFileSync(apiFile, 'utf8');

        // Simple static analysis to extract routes
        // Looks for $router->get('/api/... or $router->post('/api/...
        const routeRegex = /\$router->(get|post|put|delete|patch)\s*\(\s*['"](\/api\/[^'"]+)['"]/gi;
        let match;
        const foundRoutes = [];

        while ((match = routeRegex.exec(content)) !== null) {
            const method = match[1].toUpperCase();
            const endpoint = match[2];
            foundRoutes.push(`${method} ${endpoint}`);
        }

        const allManifestApis = [
            ...manifest.protectedApis,
            ...manifest.publicApis
        ];

        for (const route of foundRoutes) {
            const isRegistered = allManifestApis.includes(route);
            if (!isRegistered) {
                console.error(`UNREGISTERED API ROUTE: ${route}`);
            }
            expect(isRegistered, `Found unregistered API: ${route}. Add it to application-manifest.js`).toBeTruthy();
        }
    });
});
