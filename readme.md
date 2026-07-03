FinTrack Pro — Software Requirements Specification, Technical Architecture, Development Roadmap & Audit Report
________________________________________
1. Executive Summary
Project Introduction
FinTrack Pro is a full-stack Personal Finance, Expense & Investment Management System built with PHP 8, MySQL 8, and a Bootstrap 5/JavaScript frontend. It lets a user track income, expenses, budgets, savings goals, loans, and investments in one place, with analytics, reporting, and export features.
Objectives
•	Give users a single dashboard for net worth, cash flow, and financial goals
•	Enforce secure, role-based access for Users and Admins
•	Provide accurate financial analytics and exportable reports
•	Demonstrate production-grade software engineering suitable for a portfolio/resume project
Problems Being Solved
•	Fragmented tracking of money across notebooks, spreadsheets, and banking apps
•	No unified view of income, expenses, budgets, loans, and investments
•	Lack of proactive alerts (budget overruns, bill due dates)
•	No historical trend analysis for spending or investment growth
Target Users: Students, working professionals, and freelancers who want a self-hosted finance tracker.
Stakeholders: End users, System Admin, (future) financial institutions via API integration.
Scope: Authentication, expense/income tracking, budgeting, savings goals, investment tracking, loan/EMI tracking, bill reminders, reporting/analytics, admin panel, audit logging.
Out of Scope (v1): Real bank account linking (Plaid-style), automated stock price feeds, mobile native apps, multi-tenant SaaS billing.
Success Criteria
•	All core modules functional with validated CRUD operations
•	Zero critical vulnerabilities in security audit (OWASP Top 10 covered)
•	Dashboard loads under 2 seconds with realistic data volume
•	Reports export correctly to PDF/Excel/CSV
Business Value: Reusable architecture for a monetizable SaaS finance product; strong demonstration of secure full-stack engineering.
________________________________________
2. Functional Requirements
Each module below follows: Purpose, Description, Roles, Inputs, Outputs, Business Rules, Validations, Dependencies, Edge Cases, Error Handling, Success/Failure Flow.
2.1 Authentication Module
•	Purpose: Secure account access.
•	Description: Registration, login, logout, password reset, session handling.
•	Roles: User, Admin.
•	Inputs: Name, email, password, (reset) email/token.
•	Outputs: Session/JWT token, user profile.
•	Business Rules: One account per email; password must meet policy; account locks after 5 failed logins for 15 minutes.
•	Validations: Email format, password strength (8+ chars, upper/lower/number/special), duplicate email check.
•	Dependencies: users table, mail service (for reset).
•	Edge Cases: Concurrent registration with same email; expired reset token; login during lockout.
•	Error Handling: Generic "invalid credentials" message (no user enumeration); token expiry message on reset.
•	Success Flow: Register → verify (optional) → login → session created → redirect to dashboard.
•	Failure Flow: Invalid input → inline validation error; failed login → attempt counter increments → lock message after 5th try.
2.2 Dashboard Module
•	Purpose: At-a-glance financial summary.
•	Description: Cards (income, expense, savings, net worth, budget remaining, investment value) + charts (pie/bar/line, monthly trend, category breakdown).
•	Roles: User.
•	Inputs: Date range filter.
•	Outputs: Aggregated metrics, chart data (JSON via AJAX).
•	Business Rules: Figures scoped strictly to logged-in user; default range = current month.
•	Validations: Date range must be valid (start ≤ end).
•	Dependencies: expenses, income, budgets, investments tables.
•	Edge Cases: No data yet (empty state); negative net worth; large transaction volume.
•	Error Handling: Fallback "no data" UI instead of broken chart.
•	Success/Failure Flow: Load → AJAX fetch aggregates → render; on failure show retry banner.
2.3 Income Module
•	Purpose: Track all income sources.
•	Fields: Amount, date, category (salary/freelance/scholarship/business/other), description, payment method.
•	Business Rules: Amount > 0; date not in far future.
•	Validations: Numeric amount, valid date, category from allowed list.
•	Edge Cases: Backdated entries affecting past reports; duplicate entry detection (optional warning).
•	Error Handling: Field-level errors, transaction rollback on DB failure.
2.4 Expense Module
•	Purpose: Track spending by category with receipts and tags.
•	Fields: Amount, category (Food, Rent, Fuel, Shopping, Education, Medical, Entertainment, Bills, Travel, Others), date, description, receipt upload, tags, recurring flag.
•	Business Rules: Recurring expenses auto-generate next entry on due date; receipts limited to pdf/jpg/jpeg/png, max size enforced.
•	Validations: File MIME-type check (not just extension), amount > 0.
•	Edge Cases: Recurring expense modified mid-cycle; oversized/malicious file upload.
•	Error Handling: Reject invalid files with clear message; log rejected upload attempts.
2.5 Budget Module
•	Purpose: Set per-category monthly limits with alerting.
•	Business Rules: Alerts trigger at 50%, 75%, 90%, and 100%+ (exceeded) of budget.
•	Validations: Budget amount > 0, one active budget per category per month.
•	Edge Cases: Budget changed mid-month (recalculate against existing spend); category deleted while budget active.
•	Error Handling: Prevent duplicate budget creation for same category/month.
2.6 Savings Goals Module
•	Purpose: Track progress toward a target amount (e.g., Laptop ₹80,000).
•	Fields: Goal name, target amount, current amount, target date, progress %, projected completion date.
•	Business Rules: Progress bar recalculates on each contribution; projected date based on average monthly contribution.
•	Edge Cases: Goal amount reduced below current savings; goal past due date but incomplete.
2.7 Investment Tracker
•	Purpose: Track holdings across asset types (Stocks, MF, FD, Gold, Crypto, Bonds, Real Estate, PPF, EPF, SIP, NPS).
•	Fields: Buy date, buy price, quantity, current value (manual update), computed profit/loss/ROI.
•	Business Rules: ROI = (current value − buy value) / buy value × 100.
•	Validations: Quantity/price > 0; current value updates timestamped in investment_history.
•	Edge Cases: Partial sell of a holding; zero-value or delisted asset.
2.8 Loan / EMI Tracker
•	Purpose: Track loans (education, personal, home) with EMI schedule.
•	Fields: Principal, interest rate, remaining amount, EMI due date.
•	Business Rules: Remaining amount decreases with each recorded EMI payment.
•	Edge Cases: Missed EMI (flag as overdue); loan fully paid off (auto-close).
2.9 Bill Reminder Module
•	Purpose: Remind users of recurring bills before due date.
•	Business Rules: Notification sent X days before due date (configurable).
•	Edge Cases: Bill marked paid after reminder sent; recurring bill with variable amount.
2.10 Reports Module
•	Purpose: Generate daily/weekly/monthly/quarterly/yearly reports, exportable as PDF/Excel/CSV.
•	Business Rules: Reports reflect only the requesting user's data.
•	Edge Cases: Export requested with zero records; very large date range (performance).
2.11 Analytics Module
•	Purpose: Visualize income vs expense, category spend, savings rate, investment growth, cash flow, monthly trend via Chart.js.
________________________________________
3. Non-Functional Requirements
Category	Requirement
Performance	Dashboard renders < 2s under normal load; queries indexed
Scalability	Modular MVC structure allows horizontal scaling of app servers
Security	OWASP Top 10 mitigations, encrypted secrets, hashed passwords
Availability	Target 99.5% uptime in production
Reliability	Transactional DB operations; automated backups
Accessibility	WCAG 2.1 AA where feasible (contrast, labels, keyboard nav)
Maintainability	PSR coding standards, layered architecture, documented code
Portability	Runs on any LAMP-compatible stack (XAMPP for dev)
Compatibility	Chrome, Firefox, Edge, Safari (latest 2 versions)
Browser Support	Responsive down to 360px width
Mobile Support	Fully responsive; PWA-installable
SEO	Not applicable (authenticated app) beyond basic meta tags on landing page
________________________________________
4. Complete Feature Breakdown (Example: Expense Module)
Feature: Expense Tracking
 └─ Sub-Features: Add/Edit/Delete, Recurring, Receipt Upload, Tagging, Search/Filter
    └─ Components: ExpenseForm, ExpenseList, ExpenseCard, ReceiptUploader
       └─ Pages: /expenses, /expenses/add, /expenses/:id/edit
          └─ Backend APIs: POST /api/expenses, GET /api/expenses, PUT /api/expenses/:id, DELETE /api/expenses/:id
             └─ Database Tables: expenses, categories, receipts
                └─ Business Logic: recurring generation job, budget alert trigger on save
                   └─ Validation: amount, date, category, file type/size
                      └─ Permissions: owner-only access (user_id scoping)
                         └─ Notifications: budget threshold alert
                            └─ Logs: activity_logs entry on create/update/delete
                               └─ Reports: included in monthly expense report
                                  └─ Analytics: feeds category pie chart
                                     └─ Audit Trail: audit_logs entry for delete
                                        └─ Testing: form validation, upload security, CRUD tests
This same breakdown pattern applies to Income, Budget, Savings, Investment, Loan, and Bill Reminder modules.
________________________________________
5. Module-Wise Development Plan
Frontend (per module)
•	Components: Reusable form, list, card, modal components (vanilla JS or lightweight component pattern since stack is HTML/Bootstrap/JS)
•	Pages: One list page + one add/edit form per module
•	State: Managed via page-level JS objects/AJAX calls (no SPA framework in this stack)
•	UI: Bootstrap 5 grid/cards, Chart.js for graphs
•	Validation: Client-side (JS) + mandatory server-side (PHP)
•	Forms: HTML5 form validation + custom rules
•	Routing: PHP front controller or per-page routing
•	API Integration: Fetch/AJAX calls to PHP endpoints returning JSON
•	States: Loading spinners, empty states, error banners
•	Responsive Design: Mobile-first Bootstrap grid
•	Accessibility: Labeled inputs, ARIA attributes on dynamic components
Backend (per module)
•	Routes: RESTful endpoints (e.g., /api/expenses)
•	Controllers: Thin controllers delegating to services
•	Services: Business logic (budget calculation, ROI calculation, recurring generation)
•	Repositories: PDO-based data access layer
•	Authentication: Session or JWT-based middleware
•	Authorization: Role check middleware (User vs Admin)
•	Error Handling: Centralized exception handler → JSON error responses
•	Caching: Optional caching of dashboard aggregates (short TTL)
•	Rate Limiting: Applied to login and API-heavy endpoints
•	Logging: All writes logged to activity_logs/audit_logs
Database (per module)
•	Tables: As listed in Section 6
•	Relationships: Foreign keys to users.id on all user-owned tables
•	Indexes: On user_id, date, category_id for query performance
•	Constraints: NOT NULL on required fields, CHECK constraints on amounts > 0 (MySQL 8 supports CHECK)
•	Migration Plan: Versioned SQL migration scripts, applied sequentially
________________________________________
6. Database Design
Core Tables: users, expenses, income, categories, budgets, investments, investment_history, loans, emi, notifications, audit_logs, login_history, receipts, payment_methods, currencies, settings, reports, backup_logs, sessions, password_reset, activity_logs
Key Relationships
users (1) ──< (N) expenses
users (1) ──< (N) income
users (1) ──< (N) budgets
users (1) ──< (N) investments ──< (N) investment_history
users (1) ──< (N) loans ──< (N) emi
users (1) ──< (N) notifications
users (1) ──< (N) receipts (via expenses)
categories (1) ──< (N) expenses
categories (1) ──< (N) budgets
Example: expenses table
Column	Type	Notes
id	BIGINT PK AUTO_INCREMENT	
user_id	BIGINT FK → users.id	indexed
category_id	INT FK → categories.id	indexed
amount	DECIMAL(12,2)	CHECK > 0
date	DATE	indexed
description	VARCHAR(255)	nullable
receipt_id	BIGINT FK → receipts.id	nullable
is_recurring	BOOLEAN	default false
created_at / updated_at	TIMESTAMP	
Normalization: 3NF — categories, payment methods, and currencies are separate lookup tables rather than free-text fields.
Data Flow: User submits form → Controller validates → Service applies business rules → Repository writes via PDO prepared statement → Response returned as JSON → Frontend updates UI + triggers dashboard refresh.
________________________________________
7. API Documentation (Representative Endpoints)
POST /api/expenses
•	Auth: Required (session/JWT)
•	Request: { amount, category_id, date, description, receipt? }
•	Response 201: { id, amount, category, date, ... }
•	Errors: 400 (validation), 401 (unauthenticated), 413 (file too large)
GET /api/expenses?from=&to=&category=
•	Auth: Required
•	Response 200: { data: [...], total, page }
PUT /api/expenses/:id
•	Auth: Required, owner-only (403 if not owner)
•	Request: partial or full fields
•	Response 200: updated object
DELETE /api/expenses/:id
•	Auth: Required, owner-only
•	Response 204; writes to audit_logs
POST /api/auth/login
•	Request: { email, password }
•	Response 200: { token/session, user }
•	Errors: 401 invalid credentials, 423 locked account
Same pattern applies to income, budgets, investments, loans, bills, reports endpoints — each with method, auth requirement, request/response shape, and status codes.
________________________________________
8. Authentication & Authorization
•	Login: Email + password → verify hash → create session/JWT
•	Registration: Validate uniqueness → hash password → create user
•	Password Reset: Generate time-limited token → email link → verify token → set new password
•	Email Verification: Optional token-based confirmation before full access
•	Sessions: Secure, HttpOnly, regenerated on login
•	JWT (if used): Short-lived access token + refresh token rotation
•	RBAC: Two roles — User, Admin — enforced via middleware
•	Permission Matrix:
Action	User	Admin
Manage own data	✅	✅
View other users' data	❌	✅ (read-only, audited)
Block/unblock accounts	❌	✅
System settings	❌	✅
View audit logs	❌	✅
•	Account Locking: 5 failed attempts → 15-minute lock
•	MFA: Recommended future enhancement (TOTP)
•	Security Policies: Password expiry optional; forced re-login after password change
________________________________________
9. Security Audit
OWASP Top 10 Coverage
Risk	Mitigation
Injection (SQLi)	PDO prepared statements everywhere, never string-concatenated SQL
Broken Authentication	Hashed passwords (password_hash/bcrypt or Argon2), lockout policy, secure sessions
Sensitive Data Exposure	HTTPS enforced, no plaintext secrets, .env for config
XXE	Not applicable (no XML parsing planned); disable external entities if added later
Broken Access Control	Ownership checks (user_id scoping) on every query, RBAC middleware
Security Misconfiguration	Disable directory listing, hide PHP errors in production, secure headers
XSS	htmlspecialchars() on all output, Content-Security-Policy header
Insecure Deserialization	Avoid unserialize() on user input
Vulnerable Components	Composer dependencies kept current, composer audit in CI
Insufficient Logging	activity_logs + audit_logs capture all sensitive actions
Additional Checks
•	CSRF: Token per form/session, verified server-side on all state-changing requests
•	SSRF: N/A unless external URL fetch added later; validate/whitelist if introduced
•	IDOR: Every resource lookup filtered by user_id, not just by resource ID
•	Rate Limiting: Login and password-reset endpoints throttled
•	File Upload Security: Whitelist extensions (pdf/jpg/jpeg/png), verify actual MIME type, randomize filenames, store outside web root or with .htaccess deny-execute
•	Encryption: Passwords hashed, sensitive config in .env (never committed), TLS in transit
•	Secrets Management: No secrets in source control; environment variables only
•	Security Headers: Content-Security-Policy, X-Content-Type-Options: nosniff, X-Frame-Options: DENY, Strict-Transport-Security
•	Session Security: HttpOnly, Secure, SameSite=Lax/Strict cookies
•	Compliance: No PCI/health data in scope; general data-privacy hygiene applied (minimal PII, user-controlled data export/delete)
________________________________________
10. UI/UX Audit (Representative — Dashboard Page)
•	Layout: Card grid summary on top, charts below, responsive 3→2→1 column collapse
•	User Journey: Login → dashboard is default landing → clear nav to all modules
•	Accessibility: Alt text on icons, sufficient color contrast on chart palettes
•	Typography: Consistent heading scale, readable body size (≥14px)
•	Spacing: Consistent Bootstrap spacing utilities, no cramped cards
•	Navigation: Persistent sidebar/topbar, active-state highlighting
•	Consistency: Shared design tokens (colors, buttons, form styles) across modules
•	Dark Mode: Toggle with persisted preference
•	Responsive Design: Verified at 360px, 768px, 1024px, 1440px breakpoints
•	Loading/Empty/Error States: Skeleton loaders, "no data yet" illustrations, retry banners on failure
•	Modern SaaS Improvements: Sticky filters, quick-add floating button, keyboard shortcuts for power users
Same audit dimensions apply to Expense, Income, Budget, Investment, Loan, Reports pages.
________________________________________
11. Development Roadmap
Phase	Focus	Key Tasks	Deliverables	Testing
1	Infrastructure	Repo setup, XAMPP env, DB schema, .env, folder structure	Working skeleton app	Env sanity checks
2	Authentication	Register/login/reset, sessions, RBAC middleware	Secure auth system	Auth + security tests
3	Core Modules	Expense, Income, Category CRUD	Working CRUD with validation	Unit + API tests
4	Secondary Modules	Budget, Savings Goals	Budget alerts, goal tracking	Business-rule tests
5	Investments & Loans	Investment tracker, loan/EMI tracker	Full CRUD + ROI calc	Calculation accuracy tests
6	Notifications	Bill reminders, budget alerts	Email/in-app notifications	Trigger tests
7	Reports & Export	PDF/Excel/CSV export	Downloadable reports	Export integrity tests
8	Analytics	Chart.js dashboards	Interactive charts	Data accuracy tests
9	Audit & Admin	Admin panel, audit logs, backups	Admin dashboard	Access-control tests
10	Optimization & Deployment	Caching, indexing, hardening, SSL, CI/CD	Production-ready deployment	Load + security testing
Each phase's acceptance criteria: all module tests pass, no critical security findings, responsive on target breakpoints.
________________________________________
12. Folder Structure
fintrack-pro/
├── public/              # Web root — index.php, assets entry point
│   ├── index.php
│   ├── assets/          # css, js, images
│   └── uploads/         # NOT web-executable, receipts stored here (or outside root)
├── app/
│   ├── Controllers/      # Handle HTTP requests
│   ├── Services/         # Business logic (BudgetService, InvestmentService...)
│   ├── Repositories/      # PDO data access layer
│   ├── Models/            # Data entities
│   ├── Middleware/        # Auth, RBAC, CSRF, RateLimit
│   └── Helpers/            # Validators, formatters
├── config/
│   ├── database.php
│   └── app.php
├── database/
│   ├── migrations/
│   └── seeds/
├── views/                 # PHP templates / Bootstrap pages
├── tests/
├── .env
├── composer.json
└── README.md
________________________________________
13. Tech Stack Recommendations
Layer	Choice	Why
Frontend	HTML5, CSS3, Bootstrap 5, JS, Chart.js	Matches stated requirement, fast to build, no build-tooling overhead
Backend	PHP 8.x	Widely deployable, strong typing improvements in PHP 8
Data Access	PDO with prepared statements	Prevents SQL injection, DB-agnostic
Database	MySQL 8	CHECK constraints, JSON columns, window functions for reports
Auth	Native sessions or JWT (firebase/php-jwt)	Simple for a self-hosted app
Storage	Local filesystem (uploads/) with restricted execution	No cloud dependency needed for a student project
Caching	Optional file/opcache-based caching for dashboard	Keeps stack simple
Email	PHPMailer via SMTP	Standard, reliable
CI/CD	GitHub Actions	Free, integrates with GitHub
Monitoring	Basic error logging to file; optional Sentry	Lightweight footprint
________________________________________
14. Testing Strategy
•	Unit Tests: Service-layer logic (ROI calc, budget threshold logic) via PHPUnit
•	Integration Tests: Controller → Service → Repository → DB flows
•	API Tests: Endpoint contract tests (status codes, payload shape)
•	UI Tests: Manual + optional Selenium/Cypress smoke tests
•	Regression Tests: Re-run full suite before each release
•	Security Tests: SQLi/XSS/CSRF probes, auth bypass attempts
•	Performance/Load/Stress Tests: Simulate concurrent users on dashboard/report endpoints
•	Manual & UAT: Checklist-driven walkthrough of every module before sign-off
________________________________________
15. Deployment Architecture
•	Development: XAMPP local stack
•	Staging: Shared hosting or small VPS mirroring production config
•	Production: VPS/cloud instance (Apache/Nginx + PHP-FPM + MySQL)
•	CI/CD Pipeline: GitHub Actions → run tests → deploy on merge to main
•	Docker (optional): Containerize app + MySQL for reproducible environments
•	Environment Variables: .env for DB credentials, mail settings, app secrets — never committed
•	Reverse Proxy: Nginx in front of PHP-FPM
•	SSL: Let's Encrypt certificate, force HTTPS redirect
•	Monitoring: Uptime checks, error log alerts
•	Backups: Nightly automated MySQL dumps, retained on rotation
•	Disaster Recovery: Documented restore-from-backup procedure
•	Scaling Strategy: Stateless app servers behind a load balancer if traffic grows; DB read replicas if needed
________________________________________
16. Risk Analysis
Risk	Impact	Likelihood	Mitigation	Recovery
SQL injection via missed prepared statement	Critical	Low (if PDO enforced)	Code review checklist, static analysis	Patch + audit logs review
File upload exploit	High	Medium	Strict MIME/type validation, non-executable storage	Remove file, alert admin
Budget calculation error	Medium	Medium	Unit tests on financial logic	Hotfix + data correction script
Data loss	Critical	Low	Automated backups	Restore from latest backup
Session hijacking	High	Low	Secure cookies, HTTPS	Force logout all sessions
Scope creep (too many features for timeline)	Medium	High	Phased roadmap, MVP-first approach	Deprioritize non-core phases
________________________________________
17. Future Enhancements
•	Bank account linking / auto-import (Plaid-style)
•	Automated stock/crypto price feeds
•	Receipt OCR for auto-filled expense entries
•	Multi-currency real-time conversion
•	Native mobile app
•	AI-based spend categorization and forecasting
•	Multi-tenant SaaS billing tier
•	Shared/family budgets with multiple users per household
________________________________________
18. Final Technical Audit
Reviewed as Architect, Security Auditor, Performance Engineer, DevOps Engineer, UI/UX Expert, and QA Lead.
Finding	Priority
No MFA on authentication	Medium
Manual investment value updates (no live pricing) — acceptable for v1 but a scalability/accuracy gap	Low
Missing automated test suite in current spec — must be built alongside features, not after	High
File upload storage location must be verified non-executable in production	Critical
No mention of API versioning — recommend /api/v1/ prefix from day one	Medium
No rate limiting specified on report/export endpoints (potential DoS via repeated large exports)	Medium
Admin "view other users' data" needs strict audit logging to avoid abuse	High
No documented data-retention/delete policy for user data	Low
Recurring expense/budget interaction (mid-cycle edits) needs explicit business rule ownership	Medium
Backup restore procedure untested — must be drilled, not just documented	High
Overall Assessment: The architecture is sound for a resume-grade final-year project and has a credible path to a small production SaaS. The highest-priority pre-launch items are: enforce non-executable upload storage, build the automated test suite in parallel with features (not after), and audit-log all admin cross-user data access.

