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
            // Asset / material catalogue CRUD is restricted to the Central Lab
            // Peshawar lab-incharge ONLY (per SRS: other labs request, central
            // lab holds the master catalogue). Those perms are granted PER-USER
            // via the post-seed central-lab grant block below, NOT bundled with
            // the role. The lab-incharge role here keeps only the request flow
            // (inventories) plus sample/test/diary writes.
            $labInchargeWrites = [
                'add_water_samples', 'edit_water_samples', 'delete_water_samples',
                'add_water_sample_details', 'edit_water_sample_details', 'delete_water_sample_details',
                'edit_water_sample_results', 'edit_test_report',
                'edit_water_sample_invoices', 'add_water_sample_sop',
                // Inventory REQUEST flow — any lab-incharge can request items.
                'add_inventories', 'edit_inventories', 'inventory_received',
                'edit_inventory_details', 'edit_inventory_approve_status', 'edit_inventory_issue_status',
                'add_diaries', 'edit_diaries', 'add_dispatches', 'edit_dispatches',
                // NOTE: add_material / edit_material / add_*_asset_*  are
                // intentionally absent here — see central-lab-only grant below.
            ];
            $labInchargePerms = array_unique(array_merge($allReadOnly, $reportPerms, $labInchargeWrites));
            // Filter to only existing permissions (avoid 'Permission does not exist' errors)
            $labInchargePerms = Permission::whereIn('name', $labInchargePerms)->pluck('name')->toArray();

            // Per request 2026-05-17: lab-incharge should not see Diaries /
            // Dispatches or Demand & Issuance in the sidebar — those screens
            // are routed to junior-clerk and central-lab respectively. Strip
            // the gating perms before sync. The $allReadOnly glob above pulls
            // every view_*/show_* in, so we have to filter them out here.
            $labInchargeHidden = [
                'view_diaries', 'show_diaries', 'add_diaries', 'edit_diaries',
                'view_dispatches', 'show_dispatches', 'add_dispatches', 'edit_dispatches',
                'view_demands',
            ];
            $labInchargePerms = array_values(array_diff($labInchargePerms, $labInchargeHidden));

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

            // SBP submission perms — finance/regulatory writes added with the
            // finance branch. Created here via firstOrCreate so re-running the
            // seeder is idempotent. Bundle:
            //   submit_sbp_submissions: anyone allowed to deposit / record SBP
            //   verify_sbp_submissions: segregation-of-duties verifier
            //   view_sbp_submissions:   read-only auditors
            $sbpPerms = [
                'submit_sbp_submissions',
                'verify_sbp_submissions',
                'view_sbp_submissions',
            ];
            // permissions.module_id is a NOT NULL FK to modules — group SBP
            // under the existing 'invoices' module since SBP is part of the
            // finance/invoice flow. No separate sbp_submissions module is
            // needed for the admin UI today; can be promoted later.
            $invoicesModuleId = DB::table('modules')->where('name', 'invoices')->value('id');
            foreach ($sbpPerms as $name) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module_id' => $invoicesModuleId]
                );
            }

            // Dedicated per-report view perms — added 2026-05-17. The reports
            // used to share view_water_samples + view_reports, which made
            // granular per-report grants impossible (granting one revealed
            // them all in the sidebar). Each report now has its own perm and
            // gets gated on it directly.
            $reportsModuleId = DB::table('modules')->where('name', 'reports')->value('id');
            $perReportPerms = [
                'view_individual_sample_report',
                'view_gar',
                'view_gsr',
                'view_asr',
                'view_ce_wise_report',
                'view_pwr',
            ];
            foreach ($perReportPerms as $name) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module_id' => $reportsModuleId]
                );
            }
            // Backfill: every role that already holds view_water_samples gets
            // ALL the new per-report perms by default. Preserves existing
            // access for everyone; admins can then unpick individual reports
            // via the Module Access grid. junior-clerk does NOT hold
            // view_water_samples so this is a no-op for them — admin grants
            // individual reports through the UI as needed.
            // Spatie's `permission()` scope lives on the User model, not Role.
            // To find roles that hold a permission, query through the relation.
            $rolesWithSamples = Role::query()
                ->whereHas('permissions', fn ($q) => $q->where('name', 'view_water_samples'))
                ->pluck('name')->toArray();
            foreach ($rolesWithSamples as $rname) {
                $r = Role::query()->where('name', $rname)->first();
                if (!$r) continue;
                foreach ($perReportPerms as $p) {
                    if (!$r->hasPermissionTo($p)) $r->givePermissionTo($p);
                }
            }

            // view_demands — added 2026-05-17. Separates the "Demand &
            // Issuance" sidebar gate from view_inventories so admins can hide
            // D&I per-role without also hiding Stock / Inventory (those used
            // to share view_inventories). Granted to every role that already
            // has view_inventories EXCEPT lab-incharge, who is hidden per the
            // labInchargeHidden block above.
            // Note: module was renamed from 'issues' -> 'demand_and_issuance'
            // in ModuleSeeder. Lookup must use the new name on fresh installs.
            $issuanceModuleId = DB::table('modules')->where('name', 'demand_and_issuance')->value('id')
                ?? DB::table('modules')->where('name', 'issues')->value('id');
            Permission::firstOrCreate(
                ['name' => 'view_demands', 'guard_name' => 'web'],
                ['module_id' => $issuanceModuleId]
            );
            $grantViewDemandsTo = [
                'system-administrator', 'system-manager',
                'view-only-admin',      'general-view-account',
                'chief-engineer',       'superintending-engineer',
                'xen',
                // lab-incharge intentionally omitted.
            ];
            foreach ($grantViewDemandsTo as $rname) {
                $r = Role::query()->where('name', $rname)->first();
                if ($r && !$r->hasPermissionTo('view_demands')) {
                    $r->givePermissionTo('view_demands');
                }
            }

            // Phase B perms — sample-queue visibility model is now permission-
            // driven, not role-name-driven. Two complementary perms:
            //   view_own_samples_only — restrict list to created_by = self
            //   view_all_lab_samples  — see every sample at the user's lab
            // junior-clerk defaults to own_only; lab-assistant defaults to
            // all_lab. Custom roles can be granted either via the admin UI.
            $sampleVisibilityPerms = ['view_own_samples_only', 'view_all_lab_samples'];
            $waterSamplesModuleId = DB::table('modules')->where('name', 'water_samples')->value('id');
            foreach ($sampleVisibilityPerms as $name) {
                Permission::firstOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['module_id' => $waterSamplesModuleId]
                );
            }
            // Default mappings
            $jc = Role::query()->where('name', 'junior-clerk')->first();
            if ($jc) { $jc->givePermissionTo('view_own_samples_only'); }
            $la = Role::query()->where('name', 'laboratory-assistant')->first();
            if ($la) { $la->givePermissionTo('view_all_lab_samples'); }
            $li = Role::query()->where('name', 'lab-incharge')->first();
            if ($li) { $li->givePermissionTo('view_all_lab_samples'); }

            // ── junior-clerk: strip analysis-side perms ───────────────────────
            // Per SRS §1.2 junior-clerk handles sample REGISTRATION and
            // diary/dispatch only — analysis is the lab-assistant's job. The
            // legacy AssignRolePermissionsSeeder historically granted analysis
            // perms to clerks too; we revoke them here so existing DBs are
            // corrected without a full re-seed. revokePermissionTo is a no-op
            // when the role doesn't hold the perm, so this is idempotent.
            if ($jc) {
                $clerkAnalysisStrip = [
                    'add_water_sample_details',
                    'edit_water_sample_details',
                    'delete_water_sample_details',
                    'edit_water_sample_results',
                    'edit_test_report',
                ];
                $existing = Permission::whereIn('name', $clerkAnalysisStrip)->pluck('name')->toArray();
                foreach ($existing as $permName) {
                    if ($jc->hasPermissionTo($permName)) {
                        $jc->revokePermissionTo($permName);
                    }
                }
            }

            // Grant SBP perms by role. lab-incharge can submit but not verify
            // (SoD: same person can't submit + verify the same record).
            // Also backfills add_payments / add_invoices to lab-incharge —
            // the new RecordPaymentRequest / StoreClubbedInvoiceRequest gate
            // on these and the original lab-incharge bundle missed them.
            $grantByRole = [
                'system-administrator'  => ['submit_sbp_submissions', 'verify_sbp_submissions', 'view_sbp_submissions'],
                'system-manager'        => ['submit_sbp_submissions', 'verify_sbp_submissions', 'view_sbp_submissions'],
                'view-only-admin'       => ['view_sbp_submissions'],
                'general-view-account'  => ['view_sbp_submissions'],
                'lab-incharge'          => [
                    'submit_sbp_submissions', 'view_sbp_submissions',
                    'add_payments', 'edit_payments',
                    'add_invoices', 'edit_invoices',
                ],
            ];
            foreach ($grantByRole as $roleName => $perms) {
                $r = Role::query()->where('name', $roleName)->first();
                if ($r) {
                    $r->givePermissionTo($perms);
                }
            }

            // Central Lab Peshawar exclusive perms: asset / material catalogue
            // CRUD. Granted per-user (model_has_permissions pivot) to any
            // lab-incharge user pinned to the central lab via the
            // laboratory_user pivot. Other lab-incharges request only.
            $centralLabPerms = Permission::query()
                ->whereIn('name', [
                    'add_material', 'edit_material', 'add_material_logs',
                    'add_asset_logs', 'edit_asset_logs',
                    'add_asset_maintenance_logs', 'add_asset_maintenance_schedules', 'edit_asset_maintenance_schedules',
                ])
                ->pluck('name')
                ->toArray();
            $centralLabId = DB::table('laboratories')
                ->where('name', 'Centreal lab Peshawar')
                ->whereNull('deleted_at')
                ->value('id');
            if ($centralLabId && !empty($centralLabPerms)) {
                $centralUsers = \App\Models\User::role('lab-incharge')
                    ->get()
                    ->filter(fn ($u) => $u->laboratoryUser?->id === (int) $centralLabId);
                foreach ($centralUsers as $u) {
                    $u->givePermissionTo($centralLabPerms);
                }
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
