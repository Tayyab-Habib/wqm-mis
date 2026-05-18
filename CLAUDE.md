# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Repository Layout

This is a two-workspace project with **no monorepo tooling** — each workspace has its own dependency set and dev server.

- [wqm-mis-backend/](wqm-mis-backend/) — Laravel 9 / PHP 8 API (Sanctum, Spatie permission/activitylog, maatwebsite/excel, laravel-snappy for PDFs)
- [new-frontend/](new-frontend/) — Vue 3 + Vite SPA (Pinia, Vue Router, Axios, SCSS)

Both must be running locally for the SPA to function. The legacy long-form reference at [PROJECT_OVERVIEW 10.55.02.md](PROJECT_OVERVIEW%2010.55.02.md) predates the RBAC merge — trust this file first.

## Branches

The active integration branch is **`RBAC`** (PHED RBAC + portals merge). Feature work lives on per-feature branches (`finance`, `settings`, `xen-dashboard`, `ce-dashboard`, `secretary-dashboard`, `client-portal`, `KPI`, …). Treat `main` as legacy.

## Common Commands

Backend — run from [wqm-mis-backend/](wqm-mis-backend/):
- `composer install`
- `cp .env.example .env && php artisan key:generate` — first-time only
- `php artisan migrate` — apply schema. After RBAC, run `php artisan db:seed` once for `RoleSeeder → ModuleSeeder → PermissionSeeder → AssignRolePermissionsSeeder → RbacRolesExpansionSeeder → RbacRolePermissionsSeeder` plus locality seeders. **Order matters**: locality seeders must run before `PheHierarchySeeder`, which canonicalises locality FKs from the production xlsx — see [DatabaseSeeder.php](wqm-mis-backend/database/seeders/DatabaseSeeder.php).
- `php artisan serve --port=8002` — dev server (frontend hard-codes this port)
- `php artisan test` — PHPUnit suite
- `./vendor/bin/pint` — PSR-12 formatter
- `php artisan optimize:clear` — wipes route/view/config caches. **Run this after any pull**, otherwise new routes/views/casts produce stale-cache 500s.
- `php artisan route:list` — authoritative endpoint inventory

Frontend — run from [new-frontend/](new-frontend/):
- `npm install`
- `npm run dev` — Vite dev server on `:5173` with `/api` + `/sanctum` proxied to `localhost:8002`
- `npm run build` / `npm run preview`

There is no lint or test script in [new-frontend/package.json](new-frontend/package.json) — JS is unlinted.

PHP version: the project's `composer.json` says `php ^8.0.2`, but the finance + RBAC controllers use `readonly` constructor promotion which requires **PHP 8.1+**. Anything older parses fail. Frontend needs Node `^20.19 || >=22.12`.

## RBAC Architecture (the most important thing to internalise)

Every protected route flows through:

1. **`auth:sanctum`** — Bearer token from `localStorage.user.token` *and* XSRF cookie (both attached by [services/axios.js](new-frontend/src/services/axios.js)).
2. **Permission gate** via spatie/laravel-permission — `role:*` middleware on routes, or controller-level `$user->hasPermissionTo('...')`.
3. **Visibility scope** via [`App\Services\AuthScope`](wqm-mis-backend/app/Services/AuthScope.php) — applied to every list query so a lab-scoped user only sees their lab's rows. **Unscoped roles** (system-administrator, system-manager, view-only-admin, general-view-account, secretary) bypass this; **scoped roles** (lab-incharge, laboratory-assistant, junior-clerk, xen, chief-engineer, superintending-engineer) get filtered to their `visibleLabIds()` / `visibleRegionIds()` / etc.

Centralise scoping by calling `AuthScope::waterSamples($query, $user)`, `::laboratoryMaterials(...)`, `::laboratoryAssets(...)`, `::inventories(...)`. Permission alone is not enough — adding a new list endpoint without scoping leaks cross-lab data.

The 10 SRS roles (after seeding): `system-administrator, system-manager, junior-clerk, laboratory-assistant, chief-engineer, superintending-engineer, xen, lab-incharge, view-only-admin, general-view-account`. Permission counts are visible in [RbacTestUsersSeeder.php](wqm-mis-backend/database/seeders/RbacTestUsersSeeder.php).

**Test credentials** (created in local env only): every test user shares the password `Test+Rbac1=2`. Emails follow `<role>.test@mis.com` or `<role>.abbottabad@mis.com` (for the lab-slice variants).

### Frontend RBAC

[stores/useUserStore.js](new-frontend/src/stores/useUserStore.js) exposes `hasPermission(name)`, `hasRole(name)`, `hasAnyRole([])`, `canSeeModule(name)`. Use these inside `v-if` and route guards rather than reading roles off the user object directly — the store keeps the permission list flat for O(1) lookup.

## Portals & Route Prefixes

[routes/api.php](wqm-mis-backend/routes/api.php) groups endpoints by audience:

- `/api/*` (default sanctum) — main app surface
- `/api/xen/*` — XEN portal (Executive Engineer dashboard, unfit-trail, remedial actions)
- `/api/ce/*` — Chief Engineer portal (region-scoped oversight)
- `/api/secretary/*` — Secretary province-wide oversight + fate-decisions
- `/api/finance/*` — Finance module (invoices, ledger, dues, SBP, clubbed)
- `/api/client-portal/*` — Public-ish client invoice viewing (token, not sanctum) — see `client.portal` middleware
- `/api/v1/*` ([routes/newapis.php](wqm-mis-backend/routes/newapis.php)) — public versioned listing endpoints
- `routes/web.php` — server-rendered PDF endpoints (sample reports, invoices, POs, payments) via barryvdh/laravel-snappy + wkhtmltopdf

The `wkhtmltopdf` binary is *not* installed by composer in this environment — the vendor package `wemersonjanuario/wkhtmltopdf-windows` only works on Windows. On macOS/Linux dev machines install via `brew install --cask wkhtmltopdf` (or distro equivalent). The clubbed-invoice PDF endpoint falls back to HTML when the binary is missing; other PDF endpoints will 500.

## Conventions

- **Adding an API endpoint**: route inside the `auth:sanctum` group in [api.php](wqm-mis-backend/routes/api.php) (or the relevant portal prefix) → controller under `app/Http/Controllers/<Domain>/` → Form Request under `app/Http/Requests/<Domain>/` → role middleware **and** `AuthScope::xxx($query, $user)` scoping for list queries → frontend wrapper in `src/services/<domain>Service.js` (components must never call axios directly).
- **Response shape**: controllers return JSON with `message` + `data` keys; on failure `data: null` plus an HTTP status (401/403/422/500). The frontend axios interceptor unwraps to `response.data`.
- **Eloquent**: most models use `CreatedModifiedByTrait` (auto-stamps `created_by`/`modified_by`) and a `LatestScope` global scope (defaults to newest first). When you need historical rows, `->withoutGlobalScope(LatestScope::class)`.
- **Timestamps**: `TimeStampAccessorTrait` overrides `created_at` / `updated_at` accessors to return formatted strings, not Carbon. To do date math, read `getRawOriginal('created_at')` and parse with `Carbon::parse(...)` — calling `->format()` on the accessor result throws.
- **Status labels**: SRS terminology is `Unpaid / Partially Paid / Paid` (NOT pending/partial/paid). The DB stores lowercase enum values; the API translates via [`WaterSampleInvoiceStatusEnum::label()`](wqm-mis-backend/app/Enums/WaterSampleInvoiceStatusEnum.php).
- **Naming**: Models `StudlyCase` singular; controllers `PascalController`; Pinia stores `useXxxStore.js`; Vue files `PascalCase`.
- **Frontend env**: the SPA reads `VITE_APP_BASE_URL` first, then `VITE_API_BASE_URL`, then falls back to `http://127.0.0.1:8002`. Keep both names supported.

## Non-Obvious Behaviours

- **Enum casts are case-tolerant**. WaterSample's enums (`test_type`, `collected_by`, `source_type`, etc.) use [`App\Casts\TolerantEnumCast`](wqm-mis-backend/app/Casts/TolerantEnumCast.php) because legacy rows hold lowercase / empty values that the strict Spatie/PHP enum cast would crash on (e.g. `'fresh'` vs `TestFrequencyEnum::FRESH = 'Fresh'`). Strict casts silently break every JSON serialisation chain that touches a water sample.
- **WaterSample creation is multi-step in one transaction**: `WaterSampleController::store` checks lab association → finds-or-creates a Client (for PRIVATE) → creates the sample → opens a round-0 `WaterSampleTest` with `status=PENDING` → inserts `WaterSampleDetail` rows for mandatory + on-demand tests with `'NT'` placeholders → calls `GenerateWaterSampleInvoice` service. Wrapped in DB::transaction.
- **WaterSample `created` hook saves twice**: it builds a slug (`YY/<lab-code>/<PHE|PRIVATE>/<NNNN>`) and a QR code, then re-saves. The slug + QR are referenced by reports/PDFs — don't break this.
- **`desired_test` is stored as a CSV string** but exposed as an array via accessor on the WaterSample model.
- **Activity logging via Spatie/activitylog** is configured on most models via `getActivitylogOptions()`. Removing log config silently breaks admin audit views.
- **Finance module specifics**: per-lab slug counters (`laboratories.next_clubbed_seq` / `next_sbp_seq`) generate `C/26/PWR/C0001` and `SBP/26/PWR/0001` via [`FinanceSlugService`](wqm-mis-backend/app/Services/FinanceSlugService.php), guarded by `lockForUpdate()` so concurrent clubbing can't collide. Clubbed-invoice validation is in [`StoreClubbedInvoiceRequest`](wqm-mis-backend/app/Http/Requests/Finance/StoreClubbedInvoiceRequest.php) — enforces same-client, same-lab, not-already-clubbed before the controller runs.
- **Discount math**: `GenerateWaterSampleInvoice` reads the `phe-invoice-discount` setting and applies `rate × (1 − discount/100)` — i.e. "discount=20" means PHE pays 80%. Setting lookup accepts both kebab-case and the legacy "PHE Invoice Discount" Title-Case name.

## Known Schema Gotchas

- **Many 2026-05 migrations** added PHED hierarchy (`region_id`, `circle_id`, `phed_division_id`, `sub_division_id`, `hub_lab_id`) across multiple tables. Verify migration order before squashing.
- **Money columns** on `water_sample_invoices` and `water_sample_invoice_logs` are now `decimal(15,2)` (originally `decimal(8,2)`, widened to handle institutional totals > ₨999,999.99).
- The original **`sbp_submissions` migration was an empty stub** that left only `id` + `timestamps`. The 2026_05_13 backfill migration ([2026_05_13_010000_backfill_sbp_submissions_schema.php](wqm-mis-backend/database/migrations/2026_05_13_010000_backfill_sbp_submissions_schema.php)) is idempotent and recovers the missing columns on fresh installs.

## Print Output

Finance pages use a shared utility [`src/utils/printDocument.js`](new-frontend/src/utils/printDocument.js) that prints into a hidden iframe — never call `window.print()` directly because it captures the entire SPA chrome (sidebars, modals, buttons). Each finance print button renders only the relevant document via that helper.
