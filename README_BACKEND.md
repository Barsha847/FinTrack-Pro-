# FinTrack Pro Backend — Premium Architecture Setup (Phase 1)

Welcome to the backend architecture repository of **FinTrack Pro**. This document details the architectural guidelines, layout directories, configurations, coding standards, and installation procedures established during Phase 1.

---

## 1. Architectural Overview

The backend of FinTrack Pro is designed using modern software engineering patterns for a robust, decoupled, and highly testable implementation:
*   **Object-Oriented PHP (8.3+)**: Leveraging strict typing, read-only properties, constructor promotion, and union types.
*   **SOLID Principles**: Emphasizing Single Responsibility and Dependency Inversion.
*   **Layered Architecture (Separation of Concerns)**:
    *   **Controllers Layer**: Handles incoming HTTP requests, maps input parameters, delegates tasks to services, and returns a unified JSON API format.
    *   **Services Layer**: Houses the core domain logic (e.g. calculations, business rules, notification triggers).
    *   **Repositories Layer**: Encapsulates data access mechanisms (PostgreSQL/PDO prepared statements in Phase 2).
    *   **Interfaces / Dependency Inversion**: Decouples services from database adapters to ensure the storage system is easily swappable.
*   **Centralized Configuration**: All variables are loaded dynamically from environment files (`.env`) into cohesive configuration files under `config/`.
*   **Unified API Response Standard**: Every request returns a predictable response format for the frontend.

---

## 2. Directory Layout

The workspace is organized as follows:

```text
fintrack-pro/
├── app/
│   ├── Controllers/    # Receives HTTP parameters & dispatches API responses
│   │   └── HealthController.php
│   ├── Services/       # Business/Domain logic (e.g. ROI calculations, budgets)
│   │   └── Router.php  # Regex-based application routing engine
│   ├── Repositories/   # Encapsulates data access operations
│   ├── Models/         # Entity schemas and database attributes
│   ├── Middleware/     # Request filtering (Auth, RateLimiting, Session verification)
│   ├── Helpers/        # Reusable application helpers
│   │   ├── Logger.php
│   │   └── ResponseHelper.php
│   ├── Validation/     # Server-side data validation classes
│   └── Interfaces/     # Shared contract interfaces for Dependency Inversion
├── config/             # Centralized PHP arrays for settings
│   ├── app.php
│   ├── constants.php   # Application-wide default constant arrays
│   ├── database.php
│   └── mail.php
├── database/           # DB schema versioning
│   ├── migrations/     # Versioned table structures (Phase 2)
│   ├── seeders/        # Default database content seeding (Phase 2)
│   └── schema/         # Placeholder for SQL schema dumps and logs
├── routes/             # Route configurations mapping URIs to Controllers
│   └── api.php
├── storage/            # Local data directories (denied direct web access)
│   └── logs/           # Application error and status logging
├── tests/              # Test suites
│   └── verify_backend.php
├── public/             # Web root placeholder
├── vendor/             # Autoloaded Composer packages
├── .env.example        # Local configuration template (credentials omitted)
├── .gitignore          # File exclusions (vendor/, .env, logs ignored)
├── bootstrap.php       # Application environment bootstrapping
├── composer.json       # Dependencies config and PSR-4 settings
└── README_BACKEND.md   # Developer reference manual (this file)
```

---

## 3. Core Framework Subsystems

### A. Bootstrapping (`bootstrap.php`)
Responsible for:
1.  Checking that `vendor/autoload.php` is available (preventing blank pages if dependencies are missing).
2.  Loading environment variables safely using `vlucas/phpdotenv`.
3.  Defining the `config()` helper function supporting dot-notation array keys.
4.  Establishing secure session settings:
    *   `session.cookie_httponly = 1`
    *   `session.cookie_secure` (resolved dynamically based on SSL availability)
    *   `SameSite` set to `Lax`.
5.  Registering global exception and error handlers to log stack traces and return clean, masked errors in production.

### B. Standard API Response Standard (`app/Helpers/ResponseHelper.php`)
To ensure frontend-backend contract consistency, all responses follow the schema:
```json
{
  "success": true, 
  "message": "Information text",
  "data": { ... },
  "errors": { ... }
}
```
*   **Success Status**: HTTP 200/201.
*   **Validation Failures**: HTTP 422 with validation errors listed in the `errors` object.
*   **Exceptions/Critical Failures**: HTTP 500/404 with errors masked under production, and traces exposed only under development (`APP_ENV=development`).

### C. Logging Subsystem (`app/Helpers/Logger.php`)
Writes system activities to `storage/logs/app.log`.
*   **Security Masking**: Automatically detects sensitive keys (e.g. `password`, `token`, `secret`, `db_password`) in log context payloads and replaces their values with `********`.
*   **Context Capture**: Automatically appends IP Address, HTTP Request Method, and Route URIs to all logs.

### D. Routing Subsystem (`app/Services/Router.php`)
A regex-based router that supports path-matching, dynamic parameters (e.g. `/api/expenses/{id}` parsed into `$id`), and middleware injections.

---

## 4. Coding Standards

We follow PHP community standards strictly:
1.  **PSR-12**: Extended Coding Style Guide.
2.  **PSR-4**: Autoloading mapping for `App\\` to `app/`.
3.  **Strict Typing**: Every file must declare `declare(strict_types=1);` on line 1.
4.  **No Uncaught Errors**: Native PHP warnings are caught, promoted to `ErrorException`, and handled cleanly.
5.  **Sensitive Secrets**: Credentials must never be written in codebase logic; use `.env` exclusively.

---

## 5. Installation & Setup

1.  **Verify PHP Version**:
    ```bash
    php -v # Must be PHP 8.3 or higher
    ```

2.  **Install Composer Dependencies**:
    From the project root directory, run:
    ```bash
    composer install
    ```
    This downloads:
    *   `vlucas/phpdotenv` (Environment Manager)
    *   `phpmailer/phpmailer` (SMTP Mail Handler)
    *   Generates autoloader classes mapping `App\` namespaces.

3.  **Configure Environment**:
    Copy the sample configuration file to `.env`:
    ```bash
    cp .env.example .env
    ```
    Configure your specific parameters (database, mail credentials, app url).

4.  **Run Backend Verification Test**:
    Execute the validation script to verify that syntax, logging, configuration, and routing are fully functional:
    ```bash
    php tests/verify_backend.php
    ```

---

## 6. Phase 2 Roadmap Preparation

With Phase 1 complete, the foundation is fully prepared for PostgreSQL/Supabase database integration in Phase 2:
1.  **Database Migration Setup**: Write PostgreSQL scripts inside `database/migrations/` and `database/schema/`.
2.  **Entity Models & Repository Implementation**: Create models in `app/Models/` and repositories extending contracts in `app/Interfaces/` using PDO.
3.  **Middleware Integration**: Hook authentication checks into the router pipeline for protected endpoints.
