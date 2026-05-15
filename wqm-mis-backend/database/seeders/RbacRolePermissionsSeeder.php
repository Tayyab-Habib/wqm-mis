<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Assigns permissions to the 6 new roles added by RbacRolesExpansionSeeder.
 *
 * Existing roles (system-administrator, system-manager, junior-clerk,
 * laboratory-assistant) are seeded by AssignRolePermissionsSeeder and are
 * left alone here.
 *
 * Idempotent — re-running just re-syncs the same list.
 *
 * Permission bundles per SRS §1.2:
 *
 *   chief-engineer / superintending-engineer:
 *     Read-only across all data within their hierarchy scope (region / circle).
 *     The data filter is enforced separately by the AuthScope service.
 *
 *   xen:
 *     CE/SE permissions + can edit water-sample status and write complaint
 *     logs (corrective action workflow per SRS unfit-sample flow).
 *
 *   lab-incharge:
 *     Full lab-scope ops (samples, analysis, equipment, material,
 *     invoices/payments). All writes scoped to own lab(s) via pivot.
 *
 *   view-only-admin (Director Labs):
 *     Same permission set as system-administrator-read-only. Writes are
 *     blocked at the middleware level via users.is_view_only.
 *
 *   general-view-account:
 *     Same as view-only-admin but UI further restricts to users.allowed_modules.
 */
class RbacRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        try {
            DB::beginTransaction();

            // All read-only permissions (view_*, show_*) plus the report perms.
            $allReadOnly = Permission::query()
                ->where(function ($q) {
                    $q->where('name', 'like', 'view\_%')
                      ->orWhere('name', 'like', 'show\_%');
                })
                ->pluck('name')
                ->toArray();

            $reportPerms = [
                'water_quality_analysis_report',
                'laboratory_analysis_report',
                'central_water_analysis_report',
                'view_reports',
            ];

            // ── chief-engineer & superintending-engineer ──
            $ceSePerms = array_unique(array_merge($allReadOnly, $reportPerms));
            $this->syncRole('chief-engineer',          $ceSePerms);
            $this->syncRole('superintending-engineer', $ceSePerms);

            // ── xen ──
            // Includes CE/SE perms + corrective-action writes
            $xenPerms = array_unique(array_merge($ceSePerms, [
                'edit_water_samples',
                'edit_water_sample_results',
                'add_complaint_logs',
                'edit_complaint_logs',
                'edit_complaint_status',
            ]));
            $this->syncRole('xen', $xenPerms);

            // ── lab-incharge ──
            // Full lab-scope operations. Multi-lab support via laboratory_user pivot.
            // Base = all read-only perms (so locality dropdowns, tests, designations,
            // etc. all load on Sample Reg / Analysis / Equipment forms).
            // Then add lab-scope writes on top.
            $labInchargeWrites = [
                'add_water_samples', 'edit_water_samples', 'delete_water_samples',
                'add_water_sample_details', 'edit_water_sample_details', 'delete_water_sample_details',
                'edit_water_sample_results', 'edit_test_report',
                'edit_water_sample_invoices', 'add_water_sample_sop',
                'add_asset_logs', 'edit_asset_logs',
                'add_asset_maintenance_logs', 'add_asset_maintenance_schedules', 'edit_asset_maintenance_schedules',
                'add_material', 'edit_material', 'add_material_logs',
                'add_inventories', 'edit_inventories', 'inventory_received',
                'edit_inventory_details', 'edit_inventory_approve_status', 'edit_inventory_issue_status',
                'add_diaries', 'edit_diaries', 'add_dispatches', 'edit_dispatches',
            ];
            $labInchargePerms = array_unique(array_merge($allReadOnly, $reportPerms, $labInchargeWrites));
            // Filter to only existing permissions (avoid 'Permission does not exist' errors)
            $labInchargePerms = Permission::whereIn('name', $labInchargePerms)->pluck('name')->toArray();
            $this->syncRole('lab-incharge', $labInchargePerms);

            // ── view-only-admin (Director Labs) ──
            // Same as system-administrator read-only. Writes blocked by middleware.
            $viewOnlyAdminPerms = array_unique(array_merge($allReadOnly, $reportPerms));
            $this->syncRole('view-only-admin', $viewOnlyAdminPerms);

            // ── general-view-account ──
            // Same permissions as view-only-admin; UI further restricts via allowed_modules JSON.
            $this->syncRole('general-view-account', $viewOnlyAdminPerms);

            // ── Backfill missing locality/dropdown perms on legacy lab roles ──
            // junior-clerk and laboratory-assistant are seeded by the legacy
            // AssignRolePermissionsSeeder with hand-curated lists that missed
            // locality view perms (view_provinces/divisions/districts/tehsils/
            // union_councils/designations). Without these, Sample Registration
            // dropdowns 403 on the role check inside their respective FormRequests.
            // Use givePermissionTo (additive) so legacy writes are preserved.
            $localityDropdownPerms = Permission::query()
                ->whereIn('name', [
                    'view_provinces', 'view_divisions', 'view_districts',
                    'view_tehsils', 'view_union_councils', 'view_designations',
                    'view_laboratories', 'view_complaints', 'view_complaint_types',
                    'show_provinces', 'show_divisions', 'show_districts',
                    'show_tehsils', 'show_union_councils', 'show_designations',
                    'show_laboratories',
                ])
                ->pluck('name')
                ->toArray();
            foreach (['junior-clerk', 'laboratory-assistant'] as $legacyRole) {
                $r = Role::query()->where('name', $legacyRole)->first();
                if ($r && !empty($localityDropdownPerms)) {
                    $r->givePermissionTo($localityDropdownPerms);
                }
            }

            // junior-clerk is the diary/dispatch data-entry role per SRS.
            // Frontend router lets them onto /admin/diaries-dispatches; without
            // these perms the FormRequest::authorize() 403s every diary call.
            $diaryClerkPerms = Permission::query()
                ->whereIn('name', [
                    'view_diaries', 'view_dispatches',
                    'show_diaries', 'show_dispatches',
                    'add_diaries', 'add_dispatches',
                    'edit_diaries', 'edit_dispatches',
                ])
                ->pluck('name')
                ->toArray();
            $clerk = Role::query()->where('name', 'junior-clerk')->first();
            if ($clerk && !empty($diaryClerkPerms)) {
                $clerk->givePermissionTo($diaryClerkPerms);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info('RbacRolePermissionsSeeder error: '.$e->getMessage());
            throw $e;
        }
    }

    private function syncRole(string $roleName, array $permissions): void
    {
        $role = Role::query()->where('name', $roleName)->first();
        if ($role) {
            $role->syncPermissions($permissions);
        }
    }
}
