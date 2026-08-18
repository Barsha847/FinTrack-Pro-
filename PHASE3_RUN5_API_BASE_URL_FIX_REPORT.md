# PHASE 3 RUN #5 — API BASE URL / PORT MISMATCH FIX REPORT

### 1. Exact Root Cause
The hardcoded `http://localhost:8000` prefix originated exclusively in [assets/js/utils.js](file:///d:/WebzinIntern/FinTrack%20Pro/assets/js/utils.js#L424-L426) and line 446 inside the `fetchApi` function:
```javascript
// Previous code:
let targetUrl = url;
if (window.location.port !== '8000' && url.startsWith('/api/')) {
  targetUrl = 'http://localhost:8000' + url;
}
...
const refreshUrl = window.location.port !== '8000' ? 'http://localhost:8000/api/auth/refresh' : '/api/auth/refresh';
```
When running the application on port 8001 in CI (`http://127.0.0.1:8001`), `window.location.port` is `'8001'`. Because `'8001' !== '8000'` evaluated to `true`, `fetchApi` forcibly prepended `http://localhost:8000` to all relative API requests (e.g. converting `/api/auth/me` to `http://localhost:8000/api/auth/me`), causing the browser to attempt connecting to a non-existent port 8000 server and throwing `net::ERR_CONNECTION_REFUSED`.

---

### 2. Affected Request
- **Failing Request:** `http://localhost:8000/api/auth/me`
- **Error:** `net::ERR_CONNECTION_REFUSED`

---

### 3. Architecture & Resolution
The API URL resolution is now cleanly origin-relative across all environments:

- **Local Development (`http://localhost:8000`):** `fetchApi` requests `/api/...` as a relative path, which automatically resolves against origin `http://localhost:8000`.
- **CI / E2E Testing (`http://127.0.0.1:8001`):** `fetchApi` requests `/api/...` as a relative path, which automatically resolves against origin `http://127.0.0.1:8001`.
- **Production (`https://domain.com`):** `fetchApi` requests `/api/...` as a relative path, resolving to `https://domain.com/api/...` regardless of custom domains, reverse proxies, or ports.
- **External Static Dev Server (`port 5500`):** Specifically restricted to `window.location.port === '5500'` for backwards-compatibility when using standalone VS Code Live Server against a separate local PHP backend.

---

### 4. Files Modified
- [assets/js/utils.js](file:///d:/WebzinIntern/FinTrack%20Pro/assets/js/utils.js)
- [PHASE3_RUN5_API_BASE_URL_FIX_REPORT.md](file:///d:/WebzinIntern/FinTrack%20Pro/PHASE3_RUN5_API_BASE_URL_FIX_REPORT.md)

---

### 5. Production Code Changes
**Frontend JavaScript Utility Modified:**
- [assets/js/utils.js](file:///d:/WebzinIntern/FinTrack%20Pro/assets/js/utils.js): Updated `fetchApi` URL resolution logic so that `localhost:8000` is not incorrectly prepended when the app runs on valid application ports (such as port 8001 in CI or port 80/443 in production).
- No backend PHP business logic, authentication controllers, database queries, or routing mechanisms were changed.

---

### 6. Security
- No authentication or authorization gates were bypassed or mocked.
- Token handling, CSRF headers (`X-CSRF-TOKEN`), credentials (`credentials: 'include'`), and cookie management remain fully active.
- Requests to `/api/auth/me` and protected endpoints communicate directly with the live FinTrack Pro application backend.

---

### 7. Verification

| Test | Result | Notes |
|---|---|---|
| `composer validate` | **PASSED** (Exit code 0) | Schema and lockfile valid and synchronized. |
| `composer security` | **PASSED** (Exit code 0) | `composer audit` reports 0 security vulnerability advisories. |
| `composer analyse` | **PASSED** (Exit code 0) | PHPStan reports `[OK] No errors` across all 58 codebase files. |
| `composer test` | **PASSED** (Exit code 0) | PHPUnit test suite executes cleanly (8 tests, 5 assertions). |
| `Playwright` | **PASSED** (Exit code 0) | Single-server connection verified, smoke suite passes. |
| `API requests to CI server` | **PASSED** (100%) | All frontend API calls target `http://127.0.0.1:8001/api/...`. |
| `localhost:8000 requests` | **0** | Verified: exactly 0 requests attempt port 8000 during test execution. |

---

### 8. CI Readiness

**READY TO PUSH**

The hardcoded port mismatch in `assets/js/utils.js` has been diagnosed, corrected to origin-relative paths, and locally verified with Playwright against port 8001.

---

### 9. Git Safety
Confirmed:
- **No commit**
- **No push**
- **No PR**
- **No merge**
- **No deployment**
