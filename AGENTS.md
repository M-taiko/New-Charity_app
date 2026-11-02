# AGENTS.md

You are an expert in PHP, Laravel, TypeScript, and React. Write functional, maintainable, and accessible code.

---

## TypeScript

- Strict mode always; no `any` — use `unknown` for uncertain types
- `interface` for object shapes; `type` for unions and aliases
- `readonly` on immutable properties; `Readonly<T>` / `ReadonlyArray<T>` for deep immutability
- `as const` instead of enums
- Prefer `satisfies` over type assertions (`as`) to keep inference intact
- Use utility types (`Partial`, `Pick`, `Omit`, `NonNullable`, `ReturnType`) — avoid duplicating shapes
- Configure path aliases in `tsconfig.json` (`@/features/*`, `@/components/*`, `@/lib/*`) — no deep relative imports
- Never use `React.FC` — type props explicitly as a function parameter

---

## PHP / Laravel (Backend)

### Project Layout

`app/{Http,Domain,Providers}`, `database/{migrations,factories,seeders}`, `routes/{api.php,console.php}`, `config/`, `tests/{Unit,Feature}`. Laravel serves as a **pure API backend** — no Blade views for application screens.

- `app/Http/Controllers/Api/` — thin controllers only: validate request → call service/action → return a Resource
- `app/Http/Requests/` — one Form Request per API action (`StoreZakatCalculationRequest`), all validation lives here, never in controllers
- `app/Http/Resources/` — API Resource classes shape every response; never return raw Eloquent models
- `app/Http/Middleware/` — cross-cutting concerns only (auth, tenant scoping, rate limiting)
- `app/Domain/<Domain>/` — one folder per bounded domain, self-contained:
  - `Models/` — Eloquent models, thin: relationships, casts, scopes only — no business logic
  - `Services/` — business rules and orchestration; controllers and models never contain business logic
  - `Engine/` — pure, side-effect-free calculation/business-rule logic with no direct database dependency, fully unit-testable in isolation
  - `Enums/` — native PHP 8.1+ backed enums
  - `Actions/` — single-purpose invokable classes for one discrete operation (`__invoke()`), used when a full service is overkill
  - `DTOs/` — typed data-transfer objects passed between layers instead of raw arrays
  - `Repositories/` — all Eloquent query building lives here; services never call `Model::query()` directly
  - `Events/` + `Listeners/` — cross-domain side effects go through Laravel events, never direct cross-domain service calls
- `app/Providers/` — service providers; register bindings here, never in `boot()` of unrelated classes
- `database/migrations/` — one migration per schema change, named and ordered by timestamp as Laravel expects, never edited after being run in a shared environment
- `database/factories/` + `database/seeders/` — one factory per model; seeders composed per domain, not one monolithic `DatabaseSeeder`
- `tests/Unit/Domain/<Domain>/` — pure engine/unit tests, no database
- `tests/Feature/<Domain>/` — API/integration tests through the HTTP layer

### Naming

- Files: match the class name exactly — `ZakatCalculationService.php` for `class ZakatCalculationService`
- Classes: `PascalCase`; methods and variables: `camelCase`; constants: `SCREAMING_SNAKE_CASE`
- Database tables: `snake_case`, plural (`zakat_calculations`); columns: `snake_case`
- Models: singular `PascalCase` (`ZakatCalculation`) mapped to plural `snake_case` tables per Laravel convention
- Enums: `PascalCase` name, `PascalCase` cases consistently (`AssetTreatment::MarketValue`), never mixed casing across enums
- Routes: `kebab-case` URL segments (`/api/zakat-calculations/{id}/finalize`), RESTful and resourceful naming
- Form Requests: `<Verb><Domain>Request` (`StoreZakatCalculationRequest`, `UpdateZakatCalculationRequest`)
- Resources: `<Domain>Resource` / `<Domain>Collection`

### Database (Eloquent)

- Inject the repository into the service layer; services never call Eloquent directly
- All query building, eager loading, and raw queries (`DB::raw`, `whereRaw`) belong in the repository layer
- Use `DB::transaction()` for multi-write operations
- Soft deletes via Laravel's `SoftDeletes` trait where records must be recoverable; scope reads accordingly (handled automatically by the trait)
- Paginate with cursor pagination (`cursorPaginate()`) for large datasets; `paginate()` only for small, stable lists
- Always constrain selected columns (`select([...])`) when returning collections that feed a Resource — never leak sensitive columns implicitly
- Use `Decimal`/database numeric column types (`decimal:2`) for all financial amounts — never floating point
- Define explicit `$casts` on every model, including enums and decimals
- Never run raw, unparameterized SQL — always use the query builder or bound parameters

### Modules & Service Providers

- One domain per `app/Domain/<Domain>/` folder; no cross-domain folder reach-ins
- Register domain service bindings in a dedicated `<Domain>ServiceProvider` if the domain needs interface binding, not in `AppServiceProvider`
- No circular dependencies between domains — extract shared logic into a `Shared`/`Support` namespace

### Controllers

- Thin: route + extract validated input (from the Form Request) + delegate to a service or action — no business logic, no direct model queries
- Explicit HTTP status codes (`response()->json($data, 201)`) for non-200 responses
- API versioning via route prefix (`Route::prefix('v1')->group(...)`)
- Group routes by domain in `routes/api.php` via `Route::apiResource()` or explicit route groups

### Services

- All business logic lives here; throw typed exceptions (custom `Exception` subclasses) — never return error arrays
- Constructor-inject dependencies via Laravel's container; keep services mock-friendly for tests
- One responsibility per service — split read/write services for complex domains where useful
- Emit Laravel events for cross-domain side effects — no direct cross-domain service calls

### Repository Layer

- One `<Domain>Repository` per domain (or per aggregate root); the only place Eloquent query methods are called
- All query building, eager loading, and raw SQL belong here
- Services receive plain models/DTOs back — they never build queries themselves
- Always constrain returned columns explicitly when the result feeds an API response

### DTOs & Validation

- Separate request (Form Request) and response (Resource) shapes — never expose raw Eloquent models
- All validation rules live in Form Requests (`rules()`, `messages()`); never inline `$request->validate()` in a controller
- Use DTOs (plain readonly classes or spatie/laravel-data if adopted) to pass validated data between the controller and service layer instead of raw arrays
- Custom Form Request rules for anything beyond built-in validators; keep controllers free of conditional validation logic

### Configuration

- All config in `config/*.php`, read via `config('app.name')` — never `env()` outside config files
- Validate required env vars exist at boot (custom `ServiceProvider::boot()` check, or a startup command) rather than failing silently at runtime
- `.env.example` kept in sync with every new required variable

### Auth & Security

- Laravel Sanctum for SPA/token authentication between the React frontend and the API
- Policy classes (`php artisan make:policy`) for authorization — never inline `if ($user->role === ...)` checks in controllers or services
- Route-level `throttle:` middleware on all endpoints, stricter limits on authentication endpoints
- `bcrypt`/`Hash::make()` for all password hashing — never a custom implementation
- Explicit CORS allowlist in `config/cors.php` — never a wildcard origin in production
- Never log passwords, tokens, or full request payloads containing secrets

### Global Setup

- Global exception handling in `app/Exceptions/Handler.php` (or `bootstrap/app.php` in Laravel 11+): consistent JSON error shape `{ message, errors?, timestamp }`; never leak stack traces in production responses (`APP_DEBUG=false`)
- Global `ValidationException` handling returns the full field-level errors array so the frontend can map them to form fields
- A dedicated health check route (`GET /api/health`) checks DB connectivity and any critical dependency
- Register global middleware (auth, CORS, rate limiting) in `bootstrap/app.php` / `app/Http/Kernel.php` — never ad hoc per-route unless intentionally scoped

### Logging

- Laravel's `Log` facade with named channels (`Log::channel('zakat')->info(...)`) — no raw `error_log()`/`dd()`/`dump()` left in committed code
- Structured JSON logging in production (`stack`/`daily` channel configured appropriately)
- Log every unhandled exception server-side with context; never expose it in the response body

### Performance

- Laravel's cache facade (`Cache::remember()`) for expensive/repeated reads
- Queued jobs (`ShouldQueue`) for background work (emails, report generation, exports); default queue driver to `sync` or `database` unless a persistent worker is confirmed available on the target host
- Eager-load relationships (`with()`) to avoid N+1 queries; use `Model::preventLazyLoading()` in non-production environments to catch violations early
- Response compression and route/config/view caching (`config:cache`, `route:cache`) enabled for production deploys

### Testing

- Pest (preferred) or PHPUnit for all backend tests
- `tests/Unit/` — pure logic, no database, no HTTP kernel booted
- `tests/Feature/` — full HTTP request/response cycle through the API, using `RefreshDatabase`
- Factories for all test data — never hand-built arrays that drift from the schema
- Test the calculation/engine layer exhaustively since it is pure and side-effect-free

### Error Handling

- Throw typed, custom exception classes — never a bare `throw new \Exception(...)` in domain code
- Always include a descriptive `message` — no empty messages, no raw database error strings surfaced to the client
- Return the full validation `errors` object on `422` responses so the frontend maps them to fields
- Never expose stack traces, SQL, or file paths in API responses — log them server-side only

### Conventions

- `declare(strict_types=1)` at the top of every PHP file
- Named constructors / static factory methods over ambiguous `new Class(...)` calls where multiple construction paths exist
- Never return `null` implicitly from a service method meant to return a model — use `?Model` return types explicitly or throw a `ModelNotFoundException`
- All date/time values stored and transmitted as UTC ISO 8601 strings; use Carbon consistently, never raw `DateTime`
- Money/financial values always as `decimal` columns and typed as strings or a dedicated Money value object in PHP — never native `float`

---

## React (Frontend)

### Project Layout

`src/{app,pages,features/<domain>,components,lib,types,assets,styles}`, plus `vite.config.ts`.

- `app/` — app-wide setup only: `store.ts` (if Redux is adopted), `hooks.ts` (typed Redux hooks), root providers, router setup
- `pages/` — route-level components; each page composes feature components and contains no business logic itself
- `features/<domain>/` — self-contained per domain (`features/zakat-calculations/`):
  - `components/` — components scoped to this feature only
  - `hooks/` — feature-specific hooks
  - `api/` — API client calls for this feature (TanStack Query hooks)
  - `types/` — feature-specific types
  - `schemas/` — validation schemas (Zod) for this feature's forms
  - `state/` — Redux slices, if this feature has client-only UI state that needs one (see State Management)
- `components/ui/` — shadcn/ui generated primitives, left untouched by hand-editing beyond shadcn's own CLI output
- `components/shared/` — app-specific composed components used across more than one feature
- `lib/api/` — the base HTTP client (Axios/fetch instance), interceptors, auth token attachment — centralized, never instantiated ad hoc in a component
- `lib/query/` — TanStack Query client setup and shared query key factories
- `lib/utils/` — shared, pure utility functions only — no components, no side effects
- `types/` — types shared across more than one feature
- `styles/` — global CSS only (Tailwind base layer, resets)

### Naming

- Component files: `PascalCase.tsx` (`CalculationSummaryCard.tsx`), colocated with their feature
- Functions, variables, hooks: `camelCase`
- Hooks: always prefixed with `use` (`useZakatCalculation`, `useAccountClassification`)
- Non-component files/folders: `kebab-case` (`zakat-calculations/`)
- Redux slices (if adopted): `<feature>Slice.ts`, slice `name` field matches the feature in `camelCase`; selectors and actions named descriptively (`selectReviewQueueItems`, `markAdjustmentResolved`) — never generic (`setData`, `update`)
- Shared domain vocabulary matches the backend exactly (`ZakatCalculation`, `ZakatCalculationLine`) — the same concept never has a different name in the frontend than in the API/database

### Components

- Functional components only; no class components
- One component per file; colocate a component's own sub-components in the same folder only if they are not reused elsewhere
- Keep components small and composable — extract a child component rather than growing a single file past a couple hundred lines
- No inline business logic in JSX — derive values via hooks/helpers, keep the render function about rendering
- Props typed with an explicit `interface Props { ... }` per component, not `React.FC<Props>`
- Controlled components for all form inputs — no direct DOM manipulation via refs for state that belongs in React

### State Management

- **TanStack Query owns all server state** — anything fetched from the Laravel API (calculation data, classifications, accounts, reports). Never duplicate server data into component state or Redux; treat the query cache as the source of truth for server data
- **Redux Toolkit is for client-only/UI state that must be shared across many components or persist across route changes** — not a default, added only when local `useState`/`useReducer` genuinely can't do the job. Typical candidates: a multi-step wizard's in-progress, not-yet-saved state; a cross-cutting review-queue UI state; a normalized working set of pending edits before a batch submit; app-wide UI context (active company/period, notification queue)
- If no feature ends up needing cross-component shared state, skip Redux entirely — do not add it speculatively
- If adopted: `@reduxjs/toolkit` + `react-redux`, feature-based slices colocated in `features/<domain>/state/`, a minimal root `app/store.ts` that only combines slices, and typed `useAppDispatch`/`useAppSelector` from `app/hooks.ts` — the raw untyped `useDispatch`/`useSelector` are never used directly
- Use `createSlice` exclusively — never hand-written reducers or action-type string constants
- Never adopt RTK Query alongside TanStack Query — one server-state tool only, to avoid two competing caches
- Redux DevTools enabled in development via `configureStore` defaults, confirmed stripped from the production build

### Forms

- React Hook Form for all forms; Zod schemas (from `features/<domain>/schemas/`) via `zodResolver` — never manual `useState`-per-field forms for anything beyond a single input
- Centralize reusable validation schemas — no inline anonymous validators duplicated per form
- Disable submit while the form is invalid or a mutation is in flight
- Display field errors only after the field has been touched or the form has been submitted — never on a pristine field
- Move focus to the first invalid field on a failed submit

### HTTP & Data Fetching

- Centralize the base Axios/fetch instance in `lib/api/`; every feature's `api/` folder builds on top of it, never a raw `fetch()` call scattered in a component
- Always type requests and responses: `apiClient.get<ZakatCalculation[]>('/zakat-calculations')`
- TanStack Query for all server data — `useQuery`/`useMutation`, with shared query key factories in `lib/query/` to keep invalidation consistent
- Centralize the API base URL via an environment variable (`VITE_API_BASE_URL`) — never hardcoded in a component or feature file
- Auth token attached via an Axios interceptor in `lib/api/`, not manually on each call

### Routing

- React Router with lazy-loaded route components (`React.lazy` + `Suspense`) per feature/page to keep the initial bundle small
- Route-level code splitting boundaries align with `features/<domain>/`
- Guard protected routes with a dedicated wrapper component that checks auth state, not inline checks scattered per page

### Performance

- `React.lazy` + `Suspense` for non-critical, below-the-fold, or rarely-visited views
- Virtualize long lists (`@tanstack/react-virtual` or similar) instead of rendering hundreds of DOM rows
- Memoize expensive derived values with `useMemo`, expensive callbacks passed to memoized children with `useCallback` — but only where profiling shows it matters, not by default on every value
- Avoid importing an entire feature's components at the app root — that defeats route-level code splitting

### Error Handling

- A top-level React Error Boundary catches unhandled render errors and shows a recovery UI instead of a blank screen
- An Axios response interceptor normalizes API errors into a consistent shape before they reach components
- Always display the exact `message`/`errors[]` from the API response — never a generic "something went wrong" when the API provided specifics
- Field-level validation errors render next to their field (via React Hook Form's error state), not as a single toast
- Every page that performs data fetching or mutations reserves a visible region for error state, not just a console log

### Security

- Never render untrusted content via `dangerouslySetInnerHTML`; sanitize explicitly (e.g. DOMPurify) only when unavoidable
- Auth tokens stored in memory or an httpOnly cookie (via Sanctum's cookie-based SPA auth) — never in `localStorage`
- All outbound requests go through the centralized `lib/api/` client so CSRF/auth handling stays consistent

### Accessibility

- Zero AXE violations; WCAG AA minimum
- Semantic HTML first; ARIA attributes only when native semantics are insufficient
- Manage focus explicitly after dynamic content changes (dialogs, route transitions, form errors)
- All shadcn/ui components used as provided (they ship accessible primitives via Radix) — avoid overriding in ways that strip built-in keyboard/focus handling

### Internationalization

- `react-i18next` with per-feature translation resource files mirroring the feature-based structure
- RTL layout verified explicitly for Arabic — Tailwind logical properties / `dir="rtl"`, not just mirrored text
- Numbers and currency formatted consistently regardless of locale for anything that feeds a financial calculation

### Styling

- Tailwind CSS utility classes as the default; shadcn/ui components for interactive primitives (dialogs, dropdowns, forms) rather than hand-rolled equivalents
- No global CSS overrides of shadcn/ui internals — extend via Tailwind classes and the `className` prop, or shadcn's own theming (CSS variables), not `!important` overrides
- Co-locate any component-specific non-Tailwind CSS with the component, keep `styles/` for true globals only

### Testing

- Do not create test files — no `*.test.tsx`/`*.spec.tsx` files for components, hooks, or utilities

---

## Cross-Cutting Conventions

- Shared domain vocabulary is identical across the Laravel models/DB columns, API payloads, and React types/components — the same concept is never renamed between layers
- All financial amounts are `decimal` end-to-end: `decimal` columns in MySQL, string-typed decimal values over the API (never native float), parsed and displayed carefully in React without floating-point arithmetic on money
- All date/time values are UTC ISO 8601 strings over the wire; formatted/localized only at the point of display in React
- The API is the single source of truth for validation — frontend Zod schemas mirror backend Form Request rules for UX purposes, but the backend always re-validates and is authoritative
