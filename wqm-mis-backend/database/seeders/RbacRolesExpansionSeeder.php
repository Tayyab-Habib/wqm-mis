<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Adds the SRS §1.2 roles that the original RoleSeeder didn't include.
 *
 * Uses Role::firstOrCreate so it's idempotent — safe to re-run, won't
 * touch the existing 4-5 roles (system-administrator, system-manager,
 * junior-clerk, laboratory-assistant, xen-if-exists).
 *
 * Role → PHE-hierarchy-scope mapping (enforced at query level by the
 * AuthScope service in Phase 2 — this seeder only creates the role rows):
 *
 *   chief-engineer            → users.region_id        (all districts in their region)
 *   superintending-engineer   → users.circle_id        (all districts in their PHE circle)
 *   xen                       → users.phed_division_id (their PHED division)
 *   lab-incharge              → laboratory_user pivot  (one or more labs)
 *   view-only-admin           → no scope filter, all writes blocked by middleware
 *   general-view-account      → SA-selected modules via users.allowed_modules
 *
 *   (Existing) system-administrator → no scope, full access
 *   (Existing) system-manager       → similar to admin, configurable
 *   (Existing) junior-clerk         → users.laboratory_id (data entry within own lab)
 *   (Existing) laboratory-assistant → users.laboratory_id + assigned_parameters
 *
 * Client role lives separately on the clients table (user_type='client'),
 * not via Spatie — see ClientPortalAuthController.
 */
class RbacRolesExpansionSeeder extends Seeder
{
    public function run(): void
    {
        $newRoles = [
            'chief-engineer',
            'superintending-engineer',
            'xen',
            'lab-incharge',
            'view-only-admin',
            'general-view-account',
        ];

        foreach ($newRoles as $name) {
            Role::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['created_at' => now()]
            );
        }
    }
}
