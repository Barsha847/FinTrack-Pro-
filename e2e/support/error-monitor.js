const { expect } = require('@playwright/test');

/**
 * Attaches global error and response listeners to a Playwright page.
 * Fails the test if an unexpected JS exception, HTTP 404, or HTTP 500+ occurs.
 * 
 * @param {import('@playwright/test').Page} page 
 * @param {Object} options - Allows ignoring specific URLs or status codes.
 */
function monitorErrors(page, options = {}) {
    const { ignore404s = false, ignoreNetworkFailures = false } = options;

    page.on('pageerror', exception => {
        console.error(`Uncaught JavaScript Exception: ${exception.message}`);
        expect(exception.message).toBeNull(); // Fail test on runtime error
    });

    page.on('requestfailed', request => {
        if (!ignoreNetworkFailures) {
            const url = request.url();
            // Ignore external URLs if they fail (like CDNs)
            if (url.includes('localhost') || url.startsWith('/')) {
                console.error(`Network Request Failed: ${url} - ${request.failure()?.errorText}`);
                expect(request.failure()?.errorText).toBeUndefined(); // Fail test
            }
        }
    });

    page.on('response', response => {
        const status = response.status();
        const url = response.url();
        
        if (status >= 500) {
            console.error(`Unexpected Server Error on ${url}: Status ${status}`);
            expect(status).toBeLessThan(500); // Fail test
        }
        
        if (status === 404 && !ignore404s && (url.includes('localhost') || url.startsWith('/'))) {
            // Ignore common missing static assets that aren't critical
            if (!url.match(/\.(ico|map)$/i)) {
                console.error(`Unexpected 404 Not Found on ${url}`);
                expect(status).not.toBe(404); // Fail test
            }
        }
    });
}

module.exports = { monitorErrors };
