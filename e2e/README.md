# FinTrack Pro Regression Testing Guide

This directory contains the Playwright End-to-End and Security Regression suites for FinTrack Pro.

## Central Application Manifest

The architecture relies on the `fixtures/application-manifest.js` file. This is the single source of truth for test coverage.

### When adding a new FinTrack Pro module, you MUST:

1. **Register the Page**: Add your new `.html` page to `publicPages` or `protectedPages` in the manifest. If you don't, `pages-discovery.spec.js` will fail.
2. **Add Sidebar Route**: If it appears in the sidebar, add it to `sidebarRoutes`. If you don't, `sidebar-discovery.spec.js` will fail.
3. **Register API Endpoints**: Add new API routes to `protectedApis` or `publicApis`. The `api-auth.spec.js` suite will automatically test unauthenticated 401 rejection for all protected APIs. If an API is missing from the manifest, `api-discovery.spec.js` will fail.
4. **Register Interactive Elements**: Add major action buttons (`add-btn`, `submit`) to the `critical` interactions list.
5. **Write Explicit Regression Coverage**: 
   - Auth/Navigation Tests: Ensure new views redirect correctly.
   - CRUD Tests: Add a new block in `crud/crud.spec.js` for your module.
   - IDOR Tests: Add your module's endpoint to `security/idor.spec.js`.
6. **Run Local Audits**:
   - `npm run verify:all` (Runs Playwright, NPM audit, Composer audit, PHPStan, PHPUnit, and Secret Scanner).
