module.exports = {
    // 1. Pages
    publicPages: [
        'login.html',
        'signup.html',
        'forgot-password.html',
        'reset-password.html',
        'verify-email.html',
        'otp.html'
    ],
    protectedPages: [
        'dashboard.html',
        'income.html',
        'expenses.html',
        'budgets.html',
        'savings.html',
        'investments.html',
        'loans.html',
        'bills.html',
        'reports.html',
        'analytics.html',
        'settings.html',
        'profile.html',
        'notifications.html',
        'calendar.html'
    ],
    excludedPages: [
        { file: '404.html', reason: 'Static error page, tested via HTTP status logic' },
        { file: '500.html', reason: 'Static error page, tested via error monitor' },
        { file: 'maintenance.html', reason: 'Static utility page, intentionally public' }
    ],

    // 2. Sidebar Navigation
    sidebarRoutes: [
        'dashboard.html',
        'income.html',
        'expenses.html',
        'budgets.html',
        'savings.html',
        'investments.html',
        'loans.html',
        'bills.html',
        'reports.html',
        'analytics.html',
        'settings.html'
    ],

    // 3. API Endpoints
    // Used by API discovery and API security tests to verify coverage and 401s.
    protectedApis: [
        'POST /api/auth/logout',
        'GET /api/auth/me',
        'GET /api/categories',
        'GET /api/expenses', 'POST /api/expenses', 'GET /api/expenses/{id}', 'PUT /api/expenses/{id}', 'DELETE /api/expenses/{id}',
        'GET /api/income', 'POST /api/income', 'GET /api/income/{id}', 'PUT /api/income/{id}', 'DELETE /api/income/{id}',
        'GET /api/reports/summary', 'GET /api/reports/details',
        'GET /api/budgets', 'POST /api/budgets', 'GET /api/budgets/{id}', 'PUT /api/budgets/{id}', 'DELETE /api/budgets/{id}',
        'GET /api/investments', 'POST /api/investments', 'GET /api/investments/{id}', 'PUT /api/investments/{id}', 'DELETE /api/investments/{id}',
        'PUT /api/investments/{id}/value', 'GET /api/investments/{id}/history',
        'GET /api/loans', 'POST /api/loans', 'GET /api/loans/{id}', 'PUT /api/loans/{id}', 'DELETE /api/loans/{id}',
        'GET /api/loans/{loanId}/emis', 'POST /api/loans/{loanId}/emis/{emiId}/pay',
        'GET /api/bills/upcoming', 'GET /api/bills/overdue', 'GET /api/bills', 'POST /api/bills', 'GET /api/bills/{id}', 'PUT /api/bills/{id}', 'DELETE /api/bills/{id}', 'PUT /api/bills/{id}/paid',
        'GET /api/notifications/unread-count', 'PUT /api/notifications/read-all', 'GET /api/notifications', 'PUT /api/notifications/{id}/read', 'DELETE /api/notifications/{id}'
    ],
    publicApis: [
        'GET /api/health',
        'POST /api/auth/register',
        'POST /api/auth/verify-email',
        'POST /api/auth/resend-verification',
        'POST /api/auth/login',
        'GET /api/auth/session',
        'POST /api/auth/refresh',
        'GET /api/auth/csrf-token',
        'POST /api/auth/forgot-password',
        'POST /api/auth/reset-password'
    ],

    // 4. Critical Interactions (Buttons, Forms)
    // All important buttons/forms MUST be classified here. The discovery test will fail if it finds unclassified ones.
    interactions: {
        critical: [
            // Example formats: 'page.html:#elementId', 'page.html:.className', 'page.html:button[type="submit"]'
            'login.html:button[type="submit"]',
            'signup.html:button[type="submit"]',
            'dashboard.html:#sidebarToggle',
            'income.html:.add-btn',
            'expenses.html:.add-btn',
            'budgets.html:.add-btn',
            'savings.html:.add-btn',
            'investments.html:.add-btn',
            'loans.html:.add-btn',
            'bills.html:.add-btn'
        ],
        covered: [
            // Generic fallback catch-alls for items explicitly covered in bulk tests (like sidebar nav links)
            '*:a.sidebar-nav-link',
            '*:button.modal-close',
            '*:button.profile-dropdown-trigger'
        ],
        explicitlyExcluded: [
            { selector: '*:.theme-toggle-btn', reason: 'Cosmetic only, no critical logic attached.' },
            { selector: '*:button.password-toggle-btn', reason: 'Cosmetic UI helper, not a state-changing action.' }
        ]
    }
};
