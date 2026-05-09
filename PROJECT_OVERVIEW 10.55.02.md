## 1. Project Summary

- What this project does

  WQM-MIS (Water Quality Monitoring — Management Information System) is a two-part web application: a Vue 3 + Vite frontend (SPA) and a Laravel 9 backend API. It supports sample registration, laboratory analysis workflows, inventory/asset management, finance (invoices/payments), and many administrative modules and reports used by laboratories and water-scheme stakeholders.

- Tech stack

  - Frontend: Vue 3 (Composition API), Vite, Pinia for stores, Axios for HTTP, SCSS for styles.
  - Backend: Laravel 9 (PHP 8+), Eloquent ORM, Sanctum for API authentication, Spatie packages (permissions, activitylog), maatwebsite/excel, wkhtmltopdf integration, and other common Laravel packages.
  - Database: MySQL (migrations present); Eloquent models and relationships cover the schema.
  - Auth: Laravel Sanctum (stateful for browser + token-based for SPA). Frontend uses both XSRF cookie and bearer tokens stored in localStorage.
  - Hosting: Standard Laravel + static SPA deployment (no specific hosting configs present). Assets and PDF generation integrations (wkhtmltopdf/snappy) indicate server-side rendering of some reports.

- Current project status

  The codebase is a mature, production-intended project (many modules, migrations, and integrations). From the repository contents it appears actively developed (recent migrations in 2026). Status: in-development/near-production (multiple TODO migrations and ongoing schema changes exist).

---

## 2. Folder & File Structure

Below is a full tree for the two main workspaces. Every file and folder visible in the repository root is listed with a one-line description. Files marked (CRITICAL) are important for runtime or configuration. Files marked (BOILERPLATE) are standard tooling or non-business-critical.

- Root
  - `new-frontend/` — Frontend single-page application (Vue 3 + Vite).
  - `wqm-mis-backend/` — Laravel backend API and web routes.

---

- new-frontend/
  - `index.html` (CRITICAL) — SPA entry HTML used by Vite.
  - `package.json` (CRITICAL) — frontend dependencies and scripts.
  - `vite.config.js` (CRITICAL) — Vite configuration for dev/build.
  - `README.md` (BOILERPLATE) — frontend instructions.
  - `SETUP_COMPLETE.md` (BOILERPLATE) — setup marker.
  - `INTEGRATION_STATUS.md` (BOILERPLATE) — integration notes.
  - `.env` (CRITICAL if used locally) — frontend env overrides (not committed secrets expected).
  - `jsconfig.json` (BOILERPLATE) — JS/IDE path mappings.
  - `public/favicon.ico` (asset) — favicon.
  - `src/` (CRITICAL) — main application source.
    - `main.js` (CRITICAL) — app bootstrap, Pinia installation, axios CSRF fetch.
    - `App.vue` (CRITICAL) — root component that renders RouterView.
    - `router/index.js` (CRITICAL) — Vue Router routes and auth guard.
    - `store/index.js` (BOILERPLATE/compat) — legacy re-exports of Pinia stores.
    - `assets/styles/` — SCSS partials and `main.scss` (styles).
      - `_variables.scss` — design tokens.
      - `_mixins.scss` — style mixins.
      - `_global.scss` — global style overrides.
      - `main.scss` — main style entry.
    - `layouts/MainLayout.vue` — app layout with sidebar/topbar.
    - `components/` — UI components (common, reports, analysis, stock, sample-registration).
      - `common/` — shared UI primitives: `Sidebar`, `Topbar`, `Modal`, `DataTable`, `LoadingSpinner`, `FilterBar`, `RAGBadge`, `StatCard` (each with .vue and .scss files).
      - `reports/` — report-specific components: `HeatmapSVG`, `KPITable`, `ReportFilters` etc.
      - `analysis/` — analysis forms and param components: `MicrobialParams`, `ChemicalParams`, `PhysicalParams`, `QCValidator`, `AnalysisModal`.
      - `sample-registration/` — forms and cascaders: `PHEForm`, `PTForm`, `PrivateClientForm`, `LocationCascader`.
      - `stock/` — inventory/stock components: `StockRegister`, `InventoryRegister`, `StockModal`, etc.
    - `views/` — pages matching routes under `router/index.js`.
      - `Auth/Login.vue` — login page.
      - `Dashboard/Dashboard.vue` — dashboard page.
      - `WaterQuality/` — `SampleRegistration`, `AnalysisEntry`, `UnfitSampleTrail` pages.
      - `Reports/` — many reports (GAR, GSR, ASR, PWR, WSS Map, CE-wise, Individual Sample Report).
      - `Finance/` — `Invoices`, `SBPSubmissions` pages.
      - `AssetManagement/` — `StockInventory`, `EquipmentRegister`, `DemandIssuance` pages.
      - `Admin/` — `UsersHR`, `KPIFramework`, `DiariesDispatches` pages.
      - `WSSDetails/WSSDetails.vue` — water-scheme detail page.
    - `stores/` — Pinia stores (critical for client state): `useUserStore.js`, `useUiStore.js`, `useFilterStore.js`, `useSampleStore.js`, `useAssetStore.js`, `useInvoiceStore.js`, `useDiaryStore.js`, `useUnfitStore.js`, etc.
    - `composables/` — reusable Composition API hooks: `useAuth.js`, `useDashboard.js`, `useCascadeLocation.js`, `useFilters.js`, `useQcBalance.js`, `useStock.js`.
    - `services/` — API wrappers and domain services (CRITICAL): `axios.js` (configured instance), `api.js` (likely central API mapping), and domain files: `sampleService.js`, `reportService.js`, `adminService.js`, `financeService.js`, `assetService.js`, `waterSchemeService.js`, `dropdownService.js`, `wssService.js`, `dashboardService.js`, `userService.js`, `diaryService.js`.
    - `utils/` — helpers and validators: `helpers.js`, `constants.js`, `validators.js`, `exportHelpers.js`.

---

- wqm-mis-backend/
  - `.env.example` (CRITICAL) — lists environment variables used by Laravel.
  - `composer.json` (CRITICAL) — backend dependencies and autoload info.
  - `artisan` (CRITICAL) — Laravel CLI.
  - `package.json` (BOILERPLATE) — for backend frontend build hooks.
  - `README.md` (BOILERPLATE) — backend info.
  - `vite.config.js` (BOILERPLATE) — if backend serves a compiled frontend.
  - `public/index.php` (CRITICAL) — Laravel front controller.
  - `routes/` (CRITICAL) — routing definitions: `api.php`, `newapis.php`, `web.php`, `channels.php`, `console.php`.
    - `api.php` — main API endpoints, most are protected by `auth:sanctum` middleware.
    - `newapis.php` — versioned public v1 endpoints under `/v1` API prefix.
    - `web.php` — web routes for PDF generation and web views.
  - `app/` (CRITICAL) — Laravel application code.
    - `Http/Controllers/` — many controllers grouped by domain; notable ones: `AuthController`, `WaterSamples/*`, `Inventory/*`, `Assets/*`, `Reports/*`, `PurchaseOrders/*`, `Laboratories/*`, `Search/*`, `Exports/*`, `Imports/*`.
    - `Models/` — Eloquent models for every table (many subfolders: `Asset`, `Material`, `Inventory`, `WaterSamples`, `Laboratories`, `Scopes`). Models define `$fillable`, casts, and relationships — see section 4.
    - `Services/` — service classes (e.g., `GenerateWaterSampleInvoice`) used to encapsulate domain logic.
    - `Traits/` — shared model traits (e.g., `CreatedModifiedByTrait`, `TimeStampAccessorTrait`).
    - `Console/`, `Providers/`, `Notifications/`, `Policies/`, `Rules/`, `Exceptions/`, `Exports/`, `Imports/` — various Laravel artifacts used by the app.
  - `config/` (CRITICAL) — app configuration (many Laravel config files present: `app.php`, `auth.php`, `sanctum.php`, `permission.php`, `excel.php`, etc.).
  - `database/` (CRITICAL) — `migrations/`, `seeders/`, `factories/`.
    - `migrations/` — full set of migrations describing table schemas and alterations (many files; newest with 2026 timestamps). See section 4 for derived schema.
  - `resources/views/` (CRITICAL/BOILERPLATE) — email or PDF blade templates used by reports (e.g., invoice view used in `web.php` route).
  - `storage/` — runtime storage (sessions, logs, uploads).
  - `tests/` — PHPUnit tests (present but not exhaustive).

Files considered CRITICAL: any `routes/*.php`, `app/Models/*.php`, `app/Http/Controllers/**/*.php`, `database/migrations/*.php`, `config/*.php`, `composer.json`, `package.json`, `public/index.php`, `new-frontend/src/main.js`, `new-frontend/src/router/index.js`, `new-frontend/src/services/axios.js`, and `.env.example`.

---

## 3. Architecture Overview

- Frontend ↔ Backend communication

  - Primary protocol: RESTful JSON API. The frontend uses an axios instance (`new-frontend/src/services/axios.js`) with baseURL controlled by `VITE_API_BASE_URL` (defaults to http://localhost:8002). The backend exposes routes under `routes/api.php` (protected mostly by `auth:sanctum`) and public versioned endpoints in `routes/newapis.php` (prefix `/v1`).

- Auth flow

  - Backend: Laravel Sanctum is used. Routes that require authentication are grouped with `auth:sanctum` middleware in `routes/api.php`.
  - Frontend: On app startup `main.js` requests `/sanctum/csrf-cookie` on the API host to initialize CSRF cookie for stateful Sanctum flows. The axios client attaches credentials and XSRF header (reads `XSRF-TOKEN` cookie). Additionally, after login the backend's `AuthController@login` returns a personal access token (plainTextToken) attached to the user resource. The frontend stores the entire `user` object (including `token`) in localStorage and the axios request interceptor attaches `Authorization: Bearer <token>` on every request.
  - Handling 401: axios response interceptor removes `user` from localStorage and redirects to `/login` if an API call returns 401.

- Session/token management

  - Combination approach: the app supports Sanctum's stateful cookie approach (sanctum/csrf-cookie) and token-based auth via personal access tokens returned at login. Requests use both XSRF token header and Authorization bearer header when available.

- Data flow (plain text)

  1. User opens SPA -> `main.js` requests `/sanctum/csrf-cookie` for XSRF token.
  2. User logs in (`/api/login`) -> backend validates credentials (AuthController::login) and returns User resource with `token` (plainTextToken).
  3. Frontend saves user in localStorage. Axios attaches Bearer token + XSRF token on subsequent requests.
  4. User interacts with SPA -> Vue components call services in `src/services/*` which call backend REST endpoints under `/api/*`.
  5. Backend controllers validate, use services, and return JSON resources. Some actions trigger invoice generation (GenerateWaterSampleInvoice service), PDF generation (wkhtmltopdf/snappy), exports (Maatwebsite/Excel), or activity logs (spatie/activitylog).

- Third-party services integrated

  - Laravel Sanctum — authentication.
  - Spatie/laravel-permission — roles & permissions.
  - Spatie/activitylog — audit logging of model changes.
  - maatwebsite/excel — export of data to Excel.
  - SimpleSoftwareIO/qrcode — QR generation for water samples.
  - barryvdh/laravel-snappy (wkhtmltopdf) — server-side PDF generation.
  - GuzzleHttp — used indirectly by packages and possible integration points.

---

## 4. Database Schema

This section is a distilled, explicit schema derived from the Eloquent models' `$fillable` arrays and the migrations present in `database/migrations/`.

Notes on methodology and guarantees:
  - I read model `$fillable` arrays (source of truth for writable fields) and confirmed many migrations exist. Where `$fillable` and migrations disagree, migrations are the final source of truth — the repository contains many migrations describing column types and constraints. This overview lists columns that are present in `$fillable` and commonly referenced by controllers. For exact SQL types and indexes, consult the migration files in `database/migrations/` (full list present in repo).

Top-level models and important fields (primary key is `id` integer unless otherwise noted):

- users (app/Models/User.php)
  - Primary key: id (big integer, auto-increment)
  - Fields (fillable): name (string), email (string, unique), password (string), phone, image (path), gender, date_of_birth (date), date_of_joining (date), is_active (bool), employee_status, created_by, modified_by, career_background, educational_background, basic_pay_scale, designation_id (FK to designations), district_id, region_id, circle_id, phed_division_id
  - Relations: belongsTo designation, district, region, circle, phedDivision; hasMany complaints, issues, payments, inventories, inventory_logs; belongsToMany laboratories (pivot laboratory_user)
  - Timestamps: created_at, updated_at; Soft deletes: deleted_at

- water_samples (app/Models/WaterSamples/WaterSample.php)
  - Primary key: id
  - Fields (fillable): test_type, slug, qr_code, water_scheme_id (FK), sample_name, source_type, source_sub_type, water_sample_address, sampling_point, collected_by, collected_by_other, latitude (decimal), longitude (decimal), temperature_in_celsius (float), sampled_at (datetime), analyzed_at (datetime), reported_at (datetime), collected_in, collected_in_other, complaint, complaint_by_other, desired_test (string of comma separated values), created_by, modified_by, laboratory_id (FK), union_council_id, tehsil_id, district_id, division_id, province_id, region_id, circle_id, phed_division_id, hub_lab_id, sub_division_id, remarks, result, is_draft (bool), collectable_id, collectable_type (morph), lab_incharge_id, research_officer_id, current_status, current_round (int), is_closed (bool)
  - Relations: morphTo collectable (User or Client), belongsTo many locality tables, hasMany waterSampleDetails (tests), hasMany tests, hasOne waterSampleInvoice
  - Special behavior: On create the model builds `slug` like `YY/DIV/TYPE/NNNN` and generates a QR code (Qrcode facade), `desired_test` accessor returns array split by comma.
  - Timestamps & Soft deletes present.

- tests (app/Models/Test.php)
  - Fields: water_quality_parameter, type (enum), is_mandatory (bool), other test-specific metadata. Used when creating WaterSampleDetails.

- water_sample_details (app/Models/WaterSamples/WaterSampleDetail.php)
  - Fields: water_sample_id (FK), test_id (FK), water_sample_test_id (FK), input_result, analysis_result, timestamps. (Migration files show precise constraints.)

- water_sample_tests (app/Models/WaterSamples/WaterSampleTest.php)
  - Represents a testing round for a water sample: water_sample_id, round (int), status (enum), on_demand_tests, desired_test, sampling_point, collected_by, collected_in, temperature_in_celsius, sampled_at, reported_at, is_final (bool), timestamps.

- water_sample_invoices & related (Invoice, InvoiceDetail, WaterSampleInvoice, WaterSampleInvoiceLog)
  - Invoices represent billing for water samples. Fields include price, paid, balance, logs show state transitions. `GenerateWaterSampleInvoice` service performs invoice creation and association when samples are stored.

- assets & inventory (app/Models/Asset/*, Inventory/*)
  - asset: name, quantity, unit, date_of_expiry, status(enum), is_active, specification, country, agency
  - asset_logs / laboratory_asset / laboratory_asset_logs / asset_maintenance_schedules / asset_maintenance_logs: logs, maintenance schedule metadata, statuses.

- materials / laboratory_materials: inventory items for lab consumables; material_tests: mapping of materials to tests.

- locations: provinces, divisions, districts, tehsils, union_councils, circles, phed_divisions, sub_divisions
  - Each model has `name` and often `abbreviation` (division), optional coordinates (migrations added coordinates to district), and relations linking them.

- purchase_orders & purchase_order_details: PO header and line items with relations to materials or assets.

- payments & payment_details: polymorphic payments related to invoices, purchase orders, etc.

Indexes, FK and special columns
  - Primary keys: `id` (auto increment) across tables.
  - Foreign keys: are present linking samples -> water_schemes, laboratory_id -> laboratories table, created_by/modified_by -> users, many others — consult migration files under `database/migrations/` for exact constraints. Recent migrations (2026) add PHED hierarchy and hub_lab/sub_division linking.
  - Soft deletes: used on many models (User, WaterSample, Asset, LaboratoryAsset, etc.)
  - Timestamps: `created_at`, `updated_at` are standard across models.

If you need a precise column-by-column SQL specification (including types and indexes) for any table, tell me which table(s) and I'll extract the exact migration file and list columns and indexes verbatim.

---

## 5. API Reference

This project exposes a large number of endpoints. The authoritative route listing is `wqm-mis-backend/routes/api.php` and `routes/newapis.php` (versioned public endpoints). Below is a grouped summary for an AI agent. This is exhaustive for routes declared in `api.php` and `newapis.php` as present in the repository.

Notes: all routes not explicitly under `/v1` or declared without `auth:sanctum` are in `api.php`. Many are registered via `Route::apiResource` and follow RESTful conventions.

- Auth
  - POST /api/login
    - Controller: AuthController@login
    - Body: { email, password }
    - Response: 200 with { message, data: UserResource (includes token) } on success; 401 on invalid credentials.
    - Public: yes

- User (protected: auth:sanctum)
  - GET /api/user (uses auth:sanctum middleware in Route::middleware closure) — returns authenticated user.
  - Resource routes: Route::apiResource('users', UserController::class) — standard CRUD: GET /api/users, POST /api/users, GET /api/users/{user}, PUT /api/users/{user}, DELETE /api/users/{user}
  - PUT /api/user-password — UserPasswordController@update — change password.
  - GET /api/profile — UserProfileController@show
  - PUT /api/profile — UserProfileController@update

- Water samples (protected)
  - Route::apiResource('water-samples', WaterSampleController::class)
    - GET /api/water-samples — index (paginated listing, filtered by roles)
    - POST /api/water-samples — store (create sample). Body matches StoreWaterSampleRequest validation (see source under app/Http/Requests/WaterSample).
    - GET /api/water-samples/{water_sample} — show (detailed sample with relationships)
    - PUT/PATCH /api/water-samples/{water_sample} — update
    - DELETE /api/water-samples/{water_sample} — destroy (soft deletes)
  - GET /api/water-samples-queue/{isDraft?} — WaterSampleQueueController@index
  - Related routes for tests and details:
    - API resource: water-sample-details (controllers support operations except index)
    - PUT /api/water-sample-results/{water_sample} — updates results
    - POST /api/water-sample-tests/{water_sample}/retest — retest action
    - PATCH /api/water-sample-tests/{water_sample}/start — start analysis
    - PUT /api/water-sample-tests/{water_sample}/analyze — submit analysis

- Dropdowns (protected)
  - Multiple endpoints returning lists used by frontend dropdowns (e.g., GET /api/employment-status, /api/genders, /api/source-sub-types, /api/test-frequencies, /api/test-types, /api/issue-types, /api/paymentable-types, /api/complaint-status, etc.). These are mostly single-purpose controllers returning small JSON arrays.

- Reports (protected)
  - POST /api/reports/water-quality-analysis — WaterQualityAnalysisReportController
  - POST /api/reports/central-laboratory-water-quality — CentralLaboratoryWaterQualityReportController
  - POST /api/reports/laboratory-water-quality-analysis — LaboratoryWaterQualityAnalysisReportController
  - POST /api/reports/contaminant-wise/map — ContaminantWiseReportController@map

- Inventory / Assets (protected)
  - Route::apiResource('assets', AssetController::class) — CRUD for assets
  - GET /api/assets/{asset}/status/{isActive} — UpdateAssetStatusController
  - Route::apiResource('asset-logs', AssetLogController::class)
  - Route::apiResource('laboratory-assets', LaboratoryAssetController::class) — limited index/show/update
  - Asset maintenance schedules: /api/asset-maintenance-schedules and related logs

- Materials (protected)
  - Route::apiResource('materials', MaterialController::class) — restricted to role:system-administrator
  - Route::apiResource('material-logs', MaterialLogController::class)
  - Additional endpoints for laboratory-materials listing

- Inventory (protected)
  - Route::apiResource('inventories', InventoryController::class)
  - Route::apiResource('inventory-details', InventoryDetailController::class)
  - Inventory detail status updates: /api/inventory-details/{inventory_detail}/statuses/approve and /issue
  - Inventory logs: GET /api/inventory-logs/{id}

- Issues, Complaints (protected)
  - Route::apiResource('issues', IssueController::class)
  - Route::apiResource('complaints', ComplaintController::class)
  - Complaint status update: POST /api/complaints/{complaint}/status

- Purchases & Payments (protected)
  - Route::apiResource('purchase-orders', PurchaseOrderController::class) — CRUD + PDF generation route in web.php
  - Route::apiResource('payments', PaymentController::class) — CRUD operations
  - Payment details: apiResource('payment-details', PaymentDetailController::class)

- Invoices (protected)
  - Route::apiResource('invoices', InvoiceController::class) — protected by update_modified_user middleware when needed
  - Water sample invoices are handled by `WaterSampleInvoiceController` and are linked to water samples.

- Search endpoints (protected)
  - POST /api/search-material
  - POST /api/search-asset
  - POST /api/search-purchase-order
  - POST /api/search-complaint
  - POST /api/search-issue
  - POST /api/search-water-sample
  - POST /api/search-payment
  - POST /api/search-water-scheme
  - POST /api/search-water-sample-invoices
  - POST /api/search-clients
  - GET /api/organizations
  - POST /api/search-water-sample-results

- Admin (protected)
  - API resources for roles, permissions, assign role/permission, users, designations, abbreviations, settings, modules, etc.

- Web endpoints (public or internal)
  - GET /water-samples/generate-pdf/{water_sample} — returns PDF for sample report (uses wkhtmltopdf/snappy)
  - GET /water-sample-invoices/{water_sample_invoice}/pdf-report
  - GET /purchase-orders/{purchase_order}/pdf-report
  - GET /payment/{payment}/pdf-report

- Public API v1 (routes/newapis.php)
  - Prefix: /v1 — contains public endpoints for limited listing use cases: /v1/abbreviations, /v1/dashboard, /v1/district-wise-contaminants-count, /v1/listing/get-water-schemes-status-list, /v1/listing/get-water-sample-list, /v1/listing/get-water-sample-count, /v1/listing/get-locality-list, /v1/listing/water-samples/{water_sample}/reports

Protected routes: Any route inside the `Route::middleware('auth:sanctum')->group(...)` block in `routes/api.php` requires authentication. Many resource routes are protected.

If you want a machine-parsable CSV or JSON of every route (method/path/controller) I can extract the full list programmatically from `routes/api.php` and `routes/newapis.php` and provide it.

---

## 6. Frontend Structure

- Pages and what each renders (mapping based on `src/router/index.js` and view file names):
  - /login — `views/Auth/Login.vue` — login form (calls `api/login`), stores user in localStorage.
  - /dashboard — `views/Dashboard/Dashboard.vue` — main metrics, graphs, KPIs.
  - /water-quality/sample-registration — `views/WaterQuality/SampleRegistration/SampleRegistration.vue` — sample capture form and create flow.
  - /water-quality/analysis-entry — `views/WaterQuality/AnalysisEntry/AnalysisEntry.vue` — data entry for sample analysis.
  - /water-quality/unfit-sample-trail — `views/WaterQuality/UnfitSampleTrail/UnfitSampleTrail.vue` — unfit sample workflow.
  - /reports/* — many pages rendering various reports: GAR, GSR, ASR, PWR, CE-wise, WSS Map, Individual Sample Report.
  - /finance/* — `Invoices`, `SBPSubmissions` for invoice listing and submission.
  - /assets/* — stock & inventory pages, equipment register, demand & issuance.
  - /admin/* — users & HR, KPI framework, diaries & dispatches.
  - /wss-details — water scheme details page.

- Shared components and purpose
  - `Sidebar`, `Topbar` — layout navigation.
  - `FilterBar` — common filter controls used across listings and reports.
  - `DataTable` — generic table with sorting/pagination.
  - `Modal` — generic modal wrapper.
  - `StatCard`, `RAGBadge` — small UI primitives for KPIs and statuses.
  - `HeatmapSVG`, `KPITable` — report visual components.

- State management approach
  - Pinia is used as the store solution. Stores live in `src/stores/` and are named `useUserStore`, `useUiStore`, `useFilterStore`, `useSampleStore`, etc. A small `src/store/index.js` provides legacy re-exports.

- Key hooks, utilities, and helpers
  - `composables/useAuth.js` — encapsulates login/logout, user data management.
  - `composables/useCascadeLocation.js` — chain-select for province/division/district etc.
  - `services/axios.js` — configured axios instance (withCredentials, XSRF header, bearer token middleware and 401 handling) — critical for auth and all API calls.
  - `services/*.js` — domain API wrappers; these centralize endpoints used by components.
  - `utils/validators.js` — frontend form validators.

---

## 7. Backend Structure

- Controllers / route handlers
  - Controllers are grouped by domain under `app/Http/Controllers/`. The main controller buckets:
    - Auth: `AuthController` (login)
    - WaterSamples: `WaterSamples/*` contain sample listing, creation, detail, test lifecycle functions.
    - Inventory: `Inventory/*`
    - Assets: `Assets/*`
    - Materials: `Materials/*`
    - Reports: `Reports/*`
    - Purchases: `PurchaseOrders/*`
    - Exports/Imports: `Exports/*` and `Imports/*` for Excel import/export
    - Dropdown Controllers: many small controllers returning lists for select inputs.
    - Search Controllers: controllers under `Search/*` handle POST search endpoints.

- Services / business logic layer
  - `app/Services/GenerateWaterSampleInvoice.php` — encapsulates invoice generation for a water sample; used within `WaterSampleController::store`.
  - Other services present in `app/Services/` (not exhaustively read) handle domain tasks such as PDF generation, exporting, and possibly notifications.

- Middleware
  - `auth:sanctum` — protects API routes.
  - Several custom middlewares referenced: `update_modified_user` (used to stamp modified_by/created_by), role-based middleware (`role:system-administrator`) used on routes.
  - Global or controller-level validation uses Laravel Form Requests (e.g., `StoreWaterSampleRequest`, `UpdateWaterSampleRequest`, `ViewWaterSampleRequest`, `DeleteWaterSampleRequest`) and are present under `app/Http/Requests/WaterSample`.

- Error handling & logging
  - Laravel default exception handler used. Controllers catch exceptions and return standardized JSON with message and data null for errors (see `WaterSampleController::store` which logs exception via `info()` and responds 500).
  - Activity logging via Spatie/activitylog is used in many models.

- Background jobs / scheduled tasks
  - No explicit `app/Console/Commands` or queued jobs were scanned in-depth; however `SLA` notification migrations exist and notification models are present — likely scheduled tasks or cron jobs exist but were not evident in routes. If you need the scheduler/crons, I can search `app/Console` and `app/Jobs`.

---

## 8. Environment Variables

The authoritative list is `wqm-mis-backend/.env.example` and the frontend relies on `new-frontend/.env` (light usage). Below are the env vars found and where they are used:

- Backend (`wqm-mis-backend/.env.example`)
  - APP_NAME — config('app.name') — required
  - APP_ENV — environment (local/production) — required
  - APP_KEY — Laravel app key — required in production
  - APP_DEBUG — toggles debug output — optional (default true in example)
  - APP_URL — base URL of backend (used in URL generation) — required
  - APP_FRONTEND_URL — frontend host for redirects — optional but used in web.php redirect logic

  - LOG_CHANNEL, LOG_DEPRECATIONS_CHANNEL, LOG_LEVEL — logging config

  - DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD — database connection (required for runtime)

  - BROADCAST_DRIVER, CACHE_DRIVER, FILESYSTEM_DISK, QUEUE_CONNECTION, SESSION_DRIVER, SESSION_LIFETIME — runtime config

  - MEMCACHED_HOST, REDIS_HOST, REDIS_PASSWORD, REDIS_PORT — caching/queues (optional)

  - MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_ENCRYPTION, MAIL_FROM_ADDRESS, MAIL_FROM_NAME — mail / notifications (required if sending email)

  - AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET — S3 storage for file uploads (optional)

  - PUSHER_APP_* and VITE_PUSHER_* — pusher/websocket driver config (optional)

Frontend (`new-frontend/.env`)
  - VITE_API_BASE_URL — base URL for API (used by `src/services/axios.js` and `main.js`). Required to point to the backend when not using default.
  - (Other Vite env variables may be used by build but not present in the sample `.env`.)

If you need a single machine-parsable JSON of env var names and file locations I can extract them programmatically.

---

## 9. Key Business Logic

Below are the important non-obvious behaviors an agent must understand before editing:

- Water sample creation and invoice generation
  - When a sample is created (`WaterSampleController@store`) the controller:
    1. Asserts `auth()->user()` has a laboratory association (laboratoryUser). If not, it returns 403.
    2. If collectable_type is PRIVATE, it first finds or creates a `Client` record (by phone) and uses it as the morph target.
    3. Creates a `WaterSample` and immediately creates a `WaterSampleTest` (round 0) with `status = PENDING`.
    4. Builds a merged list of test IDs: mandatory test IDs (by type) + on-demand test IDs and inserts `WaterSampleDetail` rows with default 'NT' results.
    5. Calls `GenerateWaterSampleInvoice` service to create an invoice and logs.
  - Edge cases: controller checks for missing laboratory association and handles DB transactions with commit/rollback.

- Water sample slug & QR code generation
  - `WaterSample::created` hook builds a slug: `YY/<division-abbrev>/<PHE|PRIVATE>/<zero-padded-id>` and generates a QR code with a link to `url('water-samples/' . $slug)` and saves it. This means sample creation triggers a secondary save during model creation (a two-step create-save). Agents must be careful if changing created hooks.

- Test selection logic
  - When creating test rows, the app queries `Test` model for `is_mandatory` and `type` matching desired tests and then merges with on-demand tests. The code uses `withoutGlobalScope(LatestScope::class)` in some queries to ensure older test rows are available.

- Authorization logic
  - Controllers frequently check roles (`system-administrator`, `laboratory-assistant`, `junior-clerk`) to scope returned lists (e.g., WaterSampleController@index) or to allow certain updates. Role checks are done via spatie/permission on the User model.
  - `restrictRelatedWaterSample()` encapsulates who can view/update/delete a sample — system-admin bypasses restrictions; laboratory-assistant and junior_clerk are allowed depending on `created_by` or `laboratory_id` fields.

- Invoice generation and pricing
  - `GenerateWaterSampleInvoice` encapsulates how price is calculated and invoice attached. Changes to sample detail or tests should respect the invoice creation pattern.

- Activity logging
  - Many models use Spatie/activitylog to record changes. `getActivitylogOptions()` controls which fields are logged and should not be removed lightly.

---

## 10. Coding Conventions & Patterns

- Naming conventions
  - Models: StudlyCase singular (e.g., `WaterSample`, `Asset`), stored under `app/Models/*` or domain subfolders.
  - Controllers: PascalCase with domain grouping (e.g., `WaterSamples/WaterSampleController`).
  - Stores (frontend): `useXxxStore.js` using Pinia conventions.
  - Vue files: PascalCase for components and views.

- Error handling
  - Controllers return JSON with `message` and `data` fields. On failure they usually return `data: null` and appropriate HTTP status codes (403, 401, 500).
  - Exceptions inside controllers are logged via `info()` or allowed to bubble to Laravel's exception handler.

- Validation
  - Laravel Form Requests (`app/Http/Requests/`) provide request validation. Frontend uses `validators.js` for client-side checks but server-side Form Requests are authoritative.

- Patterns
  - Repository/Service pattern: business logic often lives in `app/Services/*` (e.g., invoice generation). Controllers orchestrate request->validate->service->response.
  - Eloquent models include `CreatedModifiedByTrait` to automatically set `created_by`/`modified_by` and global scopes like `LatestScope` to return latest records by default.
  - Use of Spatie packages for roles/permissions and activity logging is consistent.

- Rules to follow
  - Always use Form Requests for input validation when adding endpoints.
  - Follow the existing role & permission checks: do not bypass spatie/permission guard logic.
  - Do not change model `created` hooks or activitylog options without checking downstream code that relies on slug/qr_code or activity logs.

---

## 11. Known Issues & Tech Debt

- Schema migrations evolving
  - There are multiple recent migrations (2026) modifying PHED hierarchy and adding nullable fields. This indicates the DB schema has recently changed; be careful when running migrations on production and ensure migration order.

- Two-stage WaterSample creation
  - The model creates (`create`) then updates slug+qr code in a `created` hook which saves again. This can be surprising if hooks or observers are modified.

- Tests & coverage
  - Tests exist under `tests/` but the repository does not show exhaustive CI. Add/maintain unit/integration tests when changing business logic.

- Frontend auth storage
  - Frontend stores token in localStorage (persisted across sessions). This is convenient but has typical XSS risk; the app also uses XSRF tokens and cookies. Consider using httpOnly cookie strategy if security hardening is required.

- TODOs and risky areas
  - Many controllers catch generic exceptions and return 500 without detailed error codes — adding error codes and structured error payloads would help clients.
  - PDF generation using wkhtmltopdf may require platform-specific binaries on servers. Ensure wkhtmltopdf is installed and properly configured before deploying PDF features.

---

## 12. Agent Instructions (Critical)

These are rules for any AI agent or developer making changes to this repo. They are mandatory.

- Before making any change, re-read the relevant section of this file.
- Never modify the database schema without checking section 4 first and verifying migrations do not conflict; always add a new migration for schema changes.
- Never add an API route without following the pattern in section 5: add route in `routes/api.php`, create a Controller under `app/Http/Controllers`, add Form Request under `app/Http/Requests`, and wire permissions if it touches protected resources.
- Never change auth logic without reading section 3 (Auth flow) first. Maintain both XSRF cookie flow and token behavior unless you intentionally replace the strategy and update frontend code.
- Always follow the conventions in section 10 (Form Requests, role checks, activity logs, traits).
- Run the project's local checks before committing: `composer install`, `npm install` in `new-frontend`, run migrations on a dev DB, and verify `vite` dev server connects to backend. For PHP linting/tests run `./vendor/bin/pint` and `php artisan test` locally.
- This file (`PROJECT_OVERVIEW.md`) must be updated whenever a significant change is made to the codebase (new models, routes, or auth changes).

---

