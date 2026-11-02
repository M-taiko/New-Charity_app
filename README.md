# Naguib Selim Charity — نظام العهد والمصروفات (مؤسسة نجيب سليم الخيرية)

A complete charity-fund management system (Arabic, fully RTL) covering treasuries, custody (عهد), expenses, accounting directions, and social cases. The project has two halves working together:

1. **The new SPA frontend** — an Angular 20 + PrimeNG app in `frontend/`, built locally, packed as static files into `public/app`, and served directly by PHP at `/app` (no Node.js needed on the server). Full per-file documentation lives in [`frontend/README.md`](frontend/README.md).
2. **The Laravel 12 backend** — the complete business logic (Blade + MySQL) as-is, plus a modern JSON layer under `/api/v1` (`routes/api_v1.php`) consumed by the new frontend. The legacy Blade system keeps running in parallel at `/` until final cutover.

## Quick Start

```bash
# 1) Backend (PHP >= 8.2 + MySQL)
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000

# 2) Frontend development (optional — proxies to 127.0.0.1:8000)
cd frontend && npm install && npm start    # http://localhost:4200/app

# 3) Build the SPA and deploy it to public/app
cd frontend && npm run build && npm run pack
```

After building: the new interface is served at `/app`, the legacy Blade system stays at `/` — a safe, reversible migration path.

## Roles & Permissions

Manager (مدير), Accountant (محاسب), Agent (مندوب), Social Researcher (باحث اجتماعي), Supervisor (مشرف — read-only) — via `spatie/laravel-permission` (see `database/seeders/RoleAndPermissionSeeder.php`). The API is always the authority for validation; the frontend mirrors permissions only to improve UX.

## Demo account (`DemoDataSeeder` data)

- Email: `donia.a5ra2019@gmail.com` — Password: `123456789` (carries every role).

---

# Complete File Index

## Root-level files

| File | Description |
|---|---|
| `.env` / `.env.example` | Environment settings (DB, mail, APP_KEY) — `.example` is the reference kept in sync with every new variable |
| `.htaccess` | Root Apache rules (redirect everything into `public/`) |
| `.editorconfig` / `.gitattributes` / `.gitignore` | Editor formatting, git attributes, ignored files |
| `AGENTS.md` | AI coding-assistant instructions (PHP/Laravel + TypeScript conventions) |
| `artisan` | Laravel CLI entry point |
| `composer.json` / `composer.lock` | PHP dependencies (Laravel 12, spatie/permission, DOMPDF, …) |
| `index.php` / `server.php` | Framework request entry (`server.php` for serving without htaccess) |
| `package.json` / `package-lock.json` / `node_modules` | Build tooling for the legacy Blade assets (Vite + Tailwind 4) |
| `phpunit.xml` | Test configuration (test database) |
| `vite.config.js` | Builds `resources/css` and `resources/js` for Blade screens |
| `DatabaseSeeder.php` | Root-level copy of the main seeder (canonical copy in `database/seeders/`) |
| `seed_test_data.php` | Standalone script that seeds demo data |
| `test_db.php` / `test_treasury_donation.php` | Manual check scripts (DB connectivity / treasury donation deposit) |
| `final_test.sh` | Runs a full post-deploy verification suite |
| `serve.log` | Log of the last `php artisan serve` run |
| `TEST_DATABASE.sql` | Ready-made SQL snapshot of a test database |
| `الجمعية.xlsx` / `temp_xlsx/` | Original imported organization data and temp import folder |
| `.compat-109` / `.kilo` / `.zcode` / `.claude` / `.vscode` | Tooling and editor configuration |

### Historical task & fix reports (root)

| File | Topic |
|---|---|
| `Task.md` / `CLIENT_FEEDBACK_TASKS.md` | Task lists and client feedback (T30–T36, …) |
| `IMPLEMENTATION.md` / `IMPLEMENTATION_SUMMARY.md` / `.txt` | Implementation plan and summary |
| `PROJECT_SUMMARY.md` / `COMPLETE_SYSTEM_SUMMARY.md` / `COMPLETE_FINAL_SUMMARY.md` / `FEATURES_IMPLEMENTED.md` / `README_NEW_FEATURES.md` | System/feature overviews |
| `SETUP_GUIDE.md` / `QUICK_START.md` / `QUICK_START.txt` / `QUICK_REFERENCE.md` / `START_HERE.txt` | Getting-started guides |
| `DEPLOYMENT_SUMMARY.txt` / `FINAL_DEPLOYMENT_CHECKLIST.md` / `FINAL_DEPLOYMENT_STATUS.md` / `PRE_DEPLOYMENT.md` / `VERIFICATION_CHECKLIST.md` / `TEST_CYCLE.md` / `TEST_RESULTS.md` / `URGENT_ACTION_REQUIRED.md` | Deployment, auditing, testing |
| `HOSTING_NOTES.md` / `HOSTING_SETUP.md` / `MASARSOFT_DEPLOYMENT.md` / `HOSTING_MBSTRING_FIX.md` / `MBSTRING_POLYFILL_WORKAROUND.md` | Masarsoft hosting and the missing-mbstring workaround |
| `ACCOUNTING_FIXES_PLAN.md` / `BLADE_CALCULATION_FIX.md` / `CUSTODY_RETURN_FIX.md` / `DONATION_FIX.md` / `IMAGE_SERVING_FIX.md` / `PERSISTENT_SESSION_IMPLEMENTATION.md` / `PRODUCTION_FIXES_SUMMARY.md` / `FIXES_README.md` / `QUICK_FIX_GUIDE.txt` / `STORAGE_CONFIGURATION_FIX.md` / `STORAGE_USAGE_GUIDE.md` | Documented production-fix write-ups |
| `REFACTORING_GUIDE.md` / `PRE_IMPLEMENTATION_VERIFICATION.md` | Refactoring guide and pre-implementation verification |

---

## `app/` — Laravel logic

### `app/Http/Controllers/` — Blade controllers (legacy system)

| File | Description |
|---|---|
| `Controller.php` | Shared base controller |
| `AuthenticatedSessionController.php` / `RegisteredUserController.php` | Web login / registration / logout |
| `TreasuryController.php` | Treasuries: balance, transfers, donations, deposits & spending |
| `CustodyController.php` | Full custody lifecycle: create, accept, spend, return, close |
| `CustodyTransferController.php` | Custody transfer between agents (sender/receiver) |
| `CustodyMovementController.php` | Custody movements ledger |
| `CustodyReturnRequestController.php` | Custody return requests |
| `ExpenseController.php` | Expenses: record, edit, review, quick expense, multi-custody split |
| `ExpenseEditRequestController.php` | Expense edit-request workflow (approve/reject) |
| `ExpenseItemController.php` | Accounting-direction items (CRUD) |
| `SocialCaseController.php` | Social cases (research, approval, documents, family members) |
| `DashboardController.php` | Role-specific dashboards |
| `ReportController.php` | All reports (treasury, custody, directions, governorates, reconciliation, …) |
| `ChatController.php` / `ChatPollController.php` | Team chat and its polls |
| `BroadcastController.php` | Urgent broadcast messages |
| `NotificationController.php` | Notifications (read / mark all) |
| `TaskController.php` | Tasks and comments |
| `HrController.php` / `SalaryController.php` | HR, salaries, and allowances |
| `PurchaseRequestController.php` / `MaintenanceRequestController.php` / `SupplierController.php` | Procurement, maintenance, suppliers |
| `UserController.php` / `ProfileController.php` | User management and profile |
| `ActivityLogController.php` | Activity log |
| `SettingsController.php` | Organization settings |

### `app/Http/Controllers/Api/` — the `/api/v1` layer (new frontend)

| File | Description |
|---|---|
| `README.md` | Documentation of the API layer and its contract |
| `V1/AuthController.php` | SPA login, `/me` with roles & abilities |
| `V1/TreasuryController.php` | Treasuries / transactions / transfer / donation over JSON |
| `V1/CustodyController.php` | Custody lists, detail, movements, per-user custody balances (`user-custodies`) |
| `V1/CustodyTransferController.php` | Custody transfers |
| `V1/ExpenseController.php` | Expense list with the full T31 filter set + `filtered_total`, my-expenses, edit requests |
| `V1/ExpenseItemController.php` | Direction items |
| `V1/LookupController.php` | One cacheable call: roles, governorates, direction tree, users, expense types actually present in the DB |
| `V1/SocialCaseController.php` | Social cases (T34 governorate filter) |
| `V1/DashboardController.php` | Dashboard cards + directional stats (T33) |
| `V1/ReportController.php` | JSON reports for the new frontend |
| `V1/HrController.php` | HR & salaries |
| `V1/ProcurementController.php` | Purchase / maintenance / suppliers |
| `V1/SystemController.php` | Tasks & system endpoints for the SPA |

### `app/Models/` — Eloquent models (28)

`User`, `Treasury`, `TreasuryTransaction` (every financial movement by type), `Custody` (incl. `scopeInDirection` and remaining-balance math), `CustodyTransfer`, `CustodyReturnRequest`, `Expense` (review, approvals, line items, custody split), `ExpenseCategory` / `ExpenseItem` (the accounting-direction tree), `ExpenseEditRequest`, `SocialCase` / `SocialCaseDocument` / `FamilyMember`, `Employee` / `AttendanceRecord` / `KpiMetric` / `SalaryCalculation` / `SalaryAllowance`, `PurchaseRequest` / `MaintenanceRequest` / `Supplier`, `Task` / `TaskComment`, `ChatMessage` / `ChatPoll` / `ChatPollVote`, `Broadcast`, `Notification`, `ActivityLog`, `Setting`.

### Rest of `app/`

| Path | Description |
|---|---|
| `Services/TreasuryService.php` | The heart of all money math: treasury & custody movements inside DB transactions |
| `Services/CustodyTransferService.php` | Custody-transfer logic and amount freezing |
| `Services/ExpenseEditRequestService.php` | Expense edit-request workflow |
| `Services/SalaryCalculationService.php` | Salary & allowance calculation |
| `Services/DirectionalStatsService.php` | Direction statistics for dashboards and reports |
| `Services/NotificationService.php` / `ActivityLogService.php` / `StatusLabelService.php` | Notifications, activity log, unified status wording |
| `Support/Api/ApiList.php` | The shared list contract: pagination (max 100), sort, search, filters, `{data, meta, links}` envelope |
| `Support/Api/NotificationLink.php` | Maps a notification to an SPA link |
| `Support/ExpenseDirectionFilter.php` / `ExpenseDirectionRule.php` | Direction filtering/validation (a node + all its descendants) |
| `Support/LineItemsSanitizer.php` | Expense line-item sanitization |
| `Support/EgyptianGovernorates.php` | Egypt governorate list |
| `Support/AttachmentFileCleanup.php` | Cleanup of orphaned attachments |
| `Helpers/functions.php` / `MbstringPolyfill.php` / `StorageHelper.php` | Helpers, mbstring polyfill for the host, storage paths |
| `Http/Middleware/ApiV1Response.php` | Normalizes JSON error shapes for the v1 layer |
| `Http/Middleware/TrackLastPageUrl.php` / `RedirectIfAuthenticated.php` | Last-page tracking, guest-only guard |
| `Policies/CustodyTransferPolicy.php` | Transfer authorization policy |
| `Providers/AppServiceProvider.php` | Service registration and extra gates (e.g. `view_all_expenses`) |
| `Traits/DataTableTrait.php` / `HasStatusScopes.php` | Blade table pagination and status scopes |
| `Console/Commands/BackfillExpenseLinks.php` | Command backfilling old expenses onto directions |
| `Exceptions/TransferBalanceException.php` | Insufficient-balance exception |

---

## `bootstrap/` and `config/`

| File | Description |
|---|---|
| `bootstrap/app.php` / `providers.php` | Laravel 12 bootstrapping: middleware, exceptions, service providers |
| `bootstrap/cache/*` | Cached packages & services (generated) |
| `config/app.php` … `config/permission.php` | 12 standard config files: app, auth, cache, database, filesystems, logging, mail, queue, session, services, and spatie permissions |

## `database/`

| Path | Description |
|---|---|
| `factories/UserFactory.php` | User factory for tests |
| `seeders/DatabaseSeeder.php` | Main seeder entry point |
| `seeders/DemoDataSeeder.php` | Main treasury + demo manager account |
| `seeders/RoleAndPermissionSeeder.php` | The five roles and sixteen permissions |
| `seeders/ExpenseCategoriesSeeder.php` | The accounting-direction tree (zakat/sadaqa and their items) |
| `seeders/SocialCasesExcelSeeder.php` | Imports social cases from Excel files |

### `database/migrations/` — 73 time-ordered migrations

- **Foundation (2026_01_31):** create `users`, `cache`, `jobs`, the spatie permission tables, `treasuries`, `treasury_transactions`, `custodies`, `expenses`, `notifications`, `social_cases`, `social_case_documents`, auth tables, expense↔case foreign key, `pending_return`, case active flag, `settings`.
- **2026_02:** hidden user & profile picture; pending custody status and logo-path fix; `expense_categories` + `expense_items` trees, `custody_transfers`, expense→direction link, detailed case fields, expense tracking on treasury transactions, received-at dates, custody status enum update, `family_members`, `initiated_by`; `expense_custody` pivot (expense split T27), expense attachments, closure & transfer transaction types; transfer fields, transaction-type updates, expense approval status, `expense_edit_requests`; tasks & comments.
- **2026_04:** external donations, direction-hierarchy rebuild, expense review & line items, activity log, broadcasts, chat messages, procurement tables (requests/suppliers/maintenance), broadcast target & case phases, chat polls & votes, employees/attendance/KPIs, salary calculations (soft deletes + defaults), purchase-request treasury & category, transaction reference fields, unified case form, `treasury_id` on expenses.
- **2026_05 → 2026_09:** custody return requests, nullable custody `treasury_id`, recovery transaction type, quick-expense flag, transaction type → varchar, Excel fields on cases, transaction source as text, `cancelled` custody status, family-member status, item-code uniqueness per category, and `pending_transfer_out` (transfer freezing).
- `.gitignore` for the folder.

## `routes/`

| File | Description |
|---|---|
| `web.php` | All Blade routes + serving the SPA from `public/app` at `/app` with path-traversal protection |
| `api_v1.php` | 169 JSON routes under `/api/v1`, grouped by domain, wrapped in `ApiV1Response` middleware |
| `auth.php` / `console.php` | Web auth and command scheduling |

## `resources/` — legacy Blade screens (116 files)

- `layouts/app.blade.php` / `layouts/modern.blade.php` — the two base layouts (legacy / modern).
- `partials/` — `pagination`, `orientation-lock` (portrait-only lock), edit-request action buttons.
- `auth/` — `login`, `register`.
- `dashboard/` — modern home, per-role shortcuts (manager/accountant/agent/researcher), activities, stars partial.
- `treasury/` — list/create/show/edit/transfer + modern variants.
- `custodies/` — 14 screens: all custodies, mine, every creation flow, show/edit, movements, agent & personal requests, returns.
- `custody-transfers/` / `custody-return-requests/` — transfers and return requests.
- `expenses/` + `expense-items/` + `expense-edit-requests/` — list/create/edit/show, quick expense, direction items, edit-request workflow.
- `social-cases/` — list/create/show/edit + unified form and researcher cases.
- `reports/` — 7 reports: dashboard, agent balances, direction analytics, items, governorates, reconciliation, case expenses.
- `hr/` — dashboard, employees & form, attendance, KPIs, salaries (list/show/edit).
- `procurement/` — purchase requests, maintenance requests, suppliers.
- `tasks/`, `chat/`, `broadcasts/`, `notifications/`, `activity-logs/`, `users/` (incl. modern variants), `profile/`, `settings/`, `errors/` (403/404/419/500), `components/data-table`, `welcome`.
- `lang/ar|en/` — validation and app messages. — `css/app.css`, `js/app.js`, `js/bootstrap.js` — Vite entry points.

## `public/`, `storage/`, `tests/`

| Path | Description |
|---|---|
| `public/index.php` / `.htaccess` / `robots.txt` / `favicon.ico` / `hot` | Framework entry and server rules |
| `public/app/` | **Generated** by `npm run pack` — never hand-edited (SPA files + checksums.txt) |
| `public/storage/` → `storage/app/public` | Uploaded attachments and images |
| `storage/app/attachments/` | Expense & document attachments |
| `storage/logs/` | Laravel logs |
| `tests/Unit/ExampleTest.php`, `tests/Feature/BladeCompilationTest.php`, `Feature/ExampleTest.php`, `TestCase.php` | PHPUnit tests (Blade compilation checked for every screen) |

## `docs/` — engineering documentation

| File | Description |
|---|---|
| `adr/0001…0005` | Architecture decisions: project structure, frontend state, API strategy, auth, Blade→SPA migration strategy |
| `audits/` | Compatibility audit & prototype, security audit, UI/accessibility audit |
| `definition-of-ready.md` | Definition of ready for tasks |

## `frontend/` — the new frontend

A complete Angular 20 project — **detailed per-file documentation in [`frontend/README.md`](frontend/README.md)**.
