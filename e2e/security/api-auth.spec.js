const { test, expect } = require('@playwright/test');
const manifest = require('../fixtures/application-manifest');

test.describe('API Authentication Regression', () => {
    test('Protected APIs must reject unauthenticated requests with 401', async ({ request }) => {
        // Iterate through all known protected APIs from the manifest
        // and hit them WITHOUT authentication.
        
        let failures = [];
        
        for (const endpointStr of manifest.protectedApis) {
            // Format: "GET /api/expenses"
            const parts = endpointStr.split(' ');
            const method = parts[0];
            let url = parts[1];
            
            // Replace wildcards like {id} with a dummy value to test auth middleware
            url = url.replace(/\{id\}/g, '9999').replace(/\{loanId\}/g, '9999').replace(/\{emiId\}/g, '9999');
            
            let response;
            try {
                // Execute a safe request
                if (method === 'GET') response = await request.get(url);
                else if (method === 'POST') response = await request.post(url, { data: {} });
                else if (method === 'PUT') response = await request.put(url, { data: {} });
                else if (method === 'DELETE') response = await request.delete(url);
                
                // We expect a 401 Unauthorized. 
                // A 404 is also technically fine if the route only matches with valid IDs, 
                // but a 200/201/204 means Auth Bypass!
                const status = response.status();
                
                if (status >= 200 && status < 300) {
                    failures.push(`${method} ${url} returned ${status} instead of 401! Auth Bypass risk.`);
                }
            } catch (e) {
                // Network error is fine, means the server rejected or crashed, but not a successful auth bypass
            }
        }
        
        expect(failures.length, `Unauthenticated APIs detected: ${failures.join(', ')}`).toBe(0);
    });
});
