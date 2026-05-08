# WQM-MIS Project — Development Session Report

**Project:** Water Quality Management — Management Information System (WQM-MIS)
**Stack:** Laravel 9 (Backend) + Vue 3 / Vite (Frontend)
**Database:** MySQL via MAMP (port 3306, database: `wqm_mis`)
**Report Date:** May 7, 2026

---

## 1. Environment Setup & Project Startup

### Problem
The project could not run out of the box due to a broken PHP extension configuration.

### What Was Done

#### PHP Extension Fix
- Two PHP installations existed on the machine: **Herd Lite** (NTS build) and **Scoop** (ZTS build)
- Herd Lite's `php.ini` pointed to Scoop's extension directory, but the DLLs were incompatible (NTS vs ZTS)
- Fixed by enabling the required extensions directly in Scoop's `php.ini`:
  - `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl`, `gd`, `zip`, `intl`
- All subsequent PHP/Composer commands were run using the Scoop PHP binary explicitly:
  `C:\Users\SYED\scoop\apps\php\current\php.exe`

#### Composer Dependencies
- `vendor/` folder did not exist — ran `composer install`
- Initial run failed because `bootstrap/cache/` was a OneDrive **ReparsePoint** (symlink placeholder)
- Deleted the reparse point and recreated it as a real directory
- Composer completed successfully, all 130 packages installed

#### npm Dependencies
- `node_modules/` did not exist — ran `npm install`
- Completed with warnings (vite-plugin-vue-devtools peer dependency mismatch — non-breaking)

#### Database Connection
- Confirmed MAMP MySQL running on port **3306**
- Database `wqm_mis` already existed
- Backend `.env` was already correctly configured (`DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_USERNAME=root`, `DB_PASSWORD=root`)
- Ran `php artisan config:cache` and `php artisan package:discover` — both succeeded

---

## 2. Sample Registration Page — Dropdown Audit

### Investigation (No Changes Made)
Audited all dropdowns on the Sample Registration page to determine which are hardcoded vs. backend-driven.

**Backend-driven (API calls on mount):**

| Dropdown | Endpoint |
|---|---|
| Division | `GET /api/all-divisions` |
| District | `GET /api/all-districts` |
| PHE Division | `GET /api/phed-divisions` |
| Hub Lab | `GET /api/hub-labs` |
| Region | `GET /api/regions` |
| Circle | `GET /api/circles` |
| Province | `GET /api/provinces` |
| Water Supply Scheme (WSS) | `GET /api/all-water-schemes` |
| Container Type | `GET /api/collected-in-status` |
| Reason for Testing | `GET /api/reason-for-testing-status` |
| Existing Clients | `GET /api/get-clients` |

**Hardcoded in frontend:**
- Collection Point, Collected By, Test Type, Test Method, Physical/Chemical/Microbial parameter checklists, Heavy Metals list, PT Form options

---

## 3. Create Water Scheme — Backend Functionality Audit

### Investigation (No Changes Made)
Traced the full backend flow for `POST /api/water-schemes`:

1. **Authorization** — user must have `add_water_schemes` permission (`StoreWaterSchemeRequest::authorize`)
2. **Validation** — required: `name` (unique), `latitude`, `longitude`, `address`, `tehsil_id`, `district_id`, `division_id`, `province_id`; optional: 15+ technical fields
3. **Controller** — `WaterSchemeController::store()` calls `WaterScheme::create($validatedData)`
4. **Slug generation** — model `booted()` hook fires after insert, generates slug: `WSS-{DIVISION_ABBREVIATION}-{ZERO_PADDED_ID}` (e.g. `WSS-LHR-0042`)
5. **Middleware** — `UpdateModifiedByCreatedByFields` sets `created_by` to authenticated user ID after HTTP 201
6. **Activity Log** — `spatie/laravel-activitylog` automatically logs creation to `activity_log` table
7. **Global Scope** — `LatestScope` applied to all queries, results always ordered latest-first

---

## 4. WSS Details Page — Filter Toolbar Redesign

### Frontend Changes (`WSSDetails.vue`)

**Before:** Toolbar had only Search, All WQ Results, All Schedule Status, Export, + Add WSS

**After (matching design spec):**
- Added **All CE Regions** dropdown — loads from `GET /api/regions`
- Added **All Divisions** dropdown — loads from `GET /api/all-divisions`, cascades under selected region
- Added **All Circles** dropdown — loads from `GET /api/circles`, cascades under selected region
- Added **All Districts** dropdown — loads from `GET /api/all-districts`, cascades under selected division
- Added **All PHE Divisions** dropdown — loads from `GET /api/phed-divisions`, cascades under selected district
- All 5 new filters are wired into the `filtered` computed property
- Cascade reset logic: selecting Region resets Division/Circle/District/PHE Division; selecting Division resets District/PHE Division; selecting District resets PHE Division
- `+ Add WSS` button moved into the toolbar row (right side, after Export)

---

## 5. Add WSS Modal

### Frontend Changes (`WSSDetails.vue`)

Built a full **Create Water Scheme modal** triggered by the `+ Add WSS` button.

**Modal sections:**

| Section | Fields |
|---|---|
| Basic Information | Name ★, Address ★ |
| Location | Division ★ → District ★ → Tehsil ★ → Union Council (cascade) |
| GPS Coordinates | Latitude ★, Longitude ★ (auto-filled from district, manually editable) |
| Technical Details | Source Type, Operation, Power Input (Solar/WAPDA), Chamber, Year of Installation, Mode, Type of Machine, Pipe Type, Horse Power Motor, Capacity, Depth, Storage, Population Served, Remarks |

**Data sources:**
- Location cascade: `GET /api/locality` (returns provinces, divisions, districts, tehsils, union councils in one call)
- WSS dropdowns: `GET /api/water-schemes-dropdowns` (returns chambers, operations, source_types as enums)
- Division list reused from filter dropdowns already loaded

**Behaviour:**
- Pre-fills Division and District from logged-in user's profile
- Selecting District auto-fills Latitude/Longitude from district's coordinates
- Field-level validation errors shown inline (red text under each field)
- On success: modal closes, table reloads, toast notification appears
- On error: errors displayed inside modal, modal stays open

---

## 6. Post-Submit UX — Toast Notification & Auto-Close

### Frontend Changes (`WSSDetails.vue`)

**Before:** Success message shown inside the modal; modal stayed open after creation

**After:**
- Modal **closes immediately** on successful creation
- A **toast notification** slides in from the top-right corner:
  - Shows: `✅ Water Scheme "Name" created successfully! Code: WSS-DIV-0001`
  - Auto-dismisses after 4 seconds
  - Has a manual ✕ close button
  - Slide-in/out CSS transition (`toast-slide`)
- `onUnmounted` lifecycle hook clears the toast timer to prevent memory leaks

---

## 7. WSS Table — Data Loading Fix

### Root Cause
The table was showing "Showing 0 schemes" because `loadWss()` was calling `POST /api/search-water-scheme` which only returns `id, name, address, latitude, longitude, district_id` — none of the fields the table needs (slug, division name, source type, etc.).

### Fix (Frontend — `WSSDetails.vue`)
- Switched `loadWss()` to use `GET /api/water-schemes` (the index endpoint) which returns full records with all relationships
- Added loading state: `⏳ Loading water schemes…` while fetching
- Added error state with Retry button if API call fails
- Added empty state row in table: "No water schemes found. Try adjusting your filters." or "Click + Add WSS to create the first one."

---

## 8. Form Submission — Type Casting Fix (422 Error)

### Problem
Submitting the Add WSS modal returned HTTP 422:
```json
{
  "errors": {
    "horse_power_motor": ["The horse power motor must be a string."],
    "storage": ["The storage must be a string."],
    "capacity": ["The capacity must be a string."],
    "depth": ["The depth must be a string."],
    "population": ["The population must be a string."]
  }
}
```

### Root Cause
The backend `StoreWaterSchemeRequest` validates these 6 fields as `nullable|string`. The frontend used `<input type="number">` which sends JavaScript numbers. Laravel's `string` rule rejects numeric types.

### Fix (Frontend — `WSSDetails.vue`)
In `submitAddWss()`, before the API call, cast all 6 fields to strings:
```js
const stringFields = ['horse_power_motor', 'storage', 'capacity', 'depth', 'population', 'chamber']
stringFields.forEach(f => {
  if (payload[f] !== '' && payload[f] !== null) payload[f] = String(payload[f])
})
```
No backend changes needed.

---

## 9. PHE Divisions Filter — Backend Connection

### Problem
The **All PHE Divisions** filter dropdown loaded data but filtering the table had no effect — every WSS matched regardless of selection.

### Root Cause (Backend — `WaterSchemeController`)
The `index` method's `select()` list did not include `phed_division_id` or `operation`. The `with()` eager load did not include the `phedDivision` relationship. So every WSS record returned `phed_division_id: null`.

### Fixes

#### Backend — `WaterSchemeController.php`
Added to `select()`:
- `phed_division_id`
- `operation`

Added to `with()`:
- `phedDivision:id,name,district_id,circle_id`

#### Backend — `WaterScheme.php` (Model)
- Added `phed_division_id` to `$fillable` array
- Added `use App\Models\PhedDivision` import
- Added `phedDivision(): BelongsTo` relationship method

#### Backend — `StoreWaterSchemeRequest.php`
- Added `phed_division_id => ['nullable', 'exists:phed_divisions,id']` validation rule so the field is accepted when creating a WSS from the modal

---

## 10. Database Migration — `phed_division_id` on `water_schemes`

### Problem
After the backend fix above, `GET /api/water-schemes` returned HTTP 500:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'phed_division_id' in 'field list'
```

### Root Cause
The `phed_division_id` column existed on `water_samples` and `users` tables (from earlier migrations) but had **never been added to `water_schemes`**.

### Fix
Created and ran a new migration:

**File:** `database/migrations/2026_05_07_115943_add_phed_division_id_to_water_schemes_table.php`

```php
Schema::table('water_schemes', function (Blueprint $table) {
    $table->foreignId('phed_division_id')
        ->nullable()
        ->after('division_id')
        ->constrained('phed_divisions')
        ->restrictOnUpdate()
        ->restrictOnDelete();
});
```

- Column is `nullable` — all existing WSS records unaffected
- Foreign key constraint to `phed_divisions` table
- Migration ran successfully: `2,169ms DONE`

---

## 11. XEN Role — Audit (No Changes Made)

### Investigation
Audited the XEN role across the entire codebase.

**Findings:**

| Item | Status |
|---|---|
| `UserRoleEnum::XEN = 'xen'` defined in code | ✅ Exists |
| `xen` role in `roles` database table | ❌ Does NOT exist |
| `RoleSeeder` includes `xen` | ❌ Not seeded |
| `XenDashboardController` (3 endpoints) | ✅ Exists |
| Auto-notification on Unfit result | ✅ Coded |
| Users assigned `xen` role | ❌ None |

**XEN role hierarchy in code:**
```
CE (Chief Engineer)     → Region level
SE (Superintending Eng) → Circle level
XEN (Executive Engineer)→ PHE Division level  ← requires phed_division_id
```

**XEN Dashboard endpoints:**
- `GET /api/xen/dashboard` — unfit samples, retest queue, SLA breaches, notifications, stats
- `GET /api/xen/trail` — full history of unfit samples with timeline
- `POST /api/xen/actions/request-retest` — log corrective action and request retest

**Conclusion:** The `xen` role needs to be seeded into the `roles` table before any XEN functionality can be used. The `RoleSeeder` only seeds 4 roles (`system-administrator`, `system-manager`, `junior-clerk`, `laboratory-assistant`). Roles `ce`, `se`, `xen` are defined in the enum with ID comments (11, 12, 13) but those IDs do not exist in the database.

---

## Summary of All Files Changed

### Frontend (`new-frontend/src/views/WSSDetails/WSSDetails.vue`)
| Change | Description |
|---|---|
| Filter toolbar | Added 5 new cascade dropdowns (Region, Division, Circle, District, PHE Division) |
| `loadFilterDropdowns()` | Now fetches 5 endpoints in parallel including `/phed-divisions` |
| `loadWss()` | Switched from `POST /search-water-scheme` to `GET /water-schemes` |
| `mapWss()` | Added `phedDivisionId`, `regionId`, `divisionId`, `circleId`, `districtId` for filtering |
| `filtered` computed | Added 5 new filter conditions |
| Add WSS Modal | Full form with 4 sections, cascade location, GPS auto-fill, enum dropdowns |
| `submitAddWss()` | String casting for 6 numeric fields; closes modal on success |
| Toast notification | Slide-in success/error toast with auto-dismiss and `onUnmounted` cleanup |
| Table states | Loading spinner, error+retry, empty state message |

### Backend (`wqm-mis-backend/`)

| File | Change |
|---|---|
| `app/Http/Controllers/WaterSchemeController.php` | Added `phed_division_id`, `operation` to `select()`; added `phedDivision` to `with()` |
| `app/Models/WaterScheme.php` | Added `phed_division_id` to `$fillable`; added `PhedDivision` import; added `phedDivision()` relationship |
| `app/Http/Requests/WaterScheme/StoreWaterSchemeRequest.php` | Added `phed_division_id` validation rule |
| `database/migrations/2026_05_07_115943_add_phed_division_id_to_water_schemes_table.php` | New migration — adds nullable FK column to `water_schemes` |
| `C:\Users\SYED\scoop\apps\php\current\cli\php.ini` | Enabled `pdo_mysql`, `openssl`, `mbstring`, `fileinfo`, `curl`, `gd`, `zip`, `intl` extensions |
| `bootstrap/cache/` | Recreated as real directory (was OneDrive ReparsePoint) |

---

## Current Known Issues / Pending Items

| Issue | Details |
|---|---|
| `xen`, `ce`, `se` roles not seeded | `RoleSeeder` needs updating; XEN Dashboard is non-functional until seeded |
| Pre-existing failing migration | `2026_04_22_170108_add_hub_lab_and_sub_division_to_water_samples_table` fails with "Duplicate column" — columns already exist, migration not marked as run |
| `IndexController` missing | `routes/newapis.php` references `App\Http\Controllers\Apis\IndexController` which doesn't exist — causes `php artisan route:list` to fail |
| `phed_division_id` on existing WSS records | All existing WSS records have `phed_division_id = null` — PHE Division filter only works for newly created schemes |

---

*Report generated: May 7, 2026*
