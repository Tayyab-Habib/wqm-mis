<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Laboratories\Laboratory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds one test user for each role so RBAC can be exercised end-to-end.
 *
 * All test users share password "Test+Rbac1=2" and live inside the
 * Peshawar hierarchy slice by default so every role lands on real data
 * and no controller can null-deref a missing hierarchy id:
 *
 *   Region ('Chief Engineer Center')
 *     └─ Circle ('SE Peshawar')
 *          └─ PhedDivision ('Peshawar-I')
 *               └─ District ('Peshawar')
 *                    └─ Laboratory ('Centreal lab Peshawar')
 *
 * A second hub slice is seeded on Abbottabad so the end-to-end XEN →
 * lab-incharge → lab-assistant → clerk flow can be exercised against a
 * non-default lab. Members of that slice carry the `.abbottabad` email
 * suffix (xen.abbottabad / labincharge.abbottabad / labassistant.abbottabad
 * / clerk.abbottabad):
 *
 *   Region ('Chief Engineer (East)')
 *     └─ Circle ('SE Abbottabad')
 *          └─ PhedDivision ('Abbottabad')
 *               └─ District ('Abbottabad')
 *                    └─ Laboratory ('Abbottabad Hub lab')
 *
 * Every user gets:
 *   - district_id (NOT NULL on users)
 *   - full hierarchy ids (region_id, circle_id, phed_division_id)
 *   - an entry in the `laboratory_user` pivot pointing at Lab #1
 *
 * Even admin/manager/view-only roles get a lab pivot row — they don't need
 * scoping but some controllers (water sample registration) require a non-null
 * `laboratory_id` from $user->laboratoryUser. Attaching them eliminates the
 * "add laboratory to user first" error during testing.
 *
 * Idempotent: existing emails are updated rather than duplicated.
 */
class RbacTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();
        try {
            // ── Pinned coherent slice (see header comment) ──
            // Pin to the canonical xlsx hierarchy slice rather than raw IDs so
            // we survive PheHierarchySeeder re-runs that allocate new ids.
            $regionId      = DB::table('regions')->where('name', 'Chief Engineer Center')->value('id')
                ?? DB::table('regions')->value('id');
            $circleId      = DB::table('circles')->where('name', 'SE Peshawar')->value('id')
                ?? DB::table('circles')->orderByDesc('id')->value('id');
            $phedDivId     = DB::table('phed_divisions')->where('name', 'Peshawar-I')->value('id')
                ?? DB::table('phed_divisions')->orderByDesc('id')->value('id');
            $districtId    = DB::table('districts')->where('name', 'Peshawar')->whereNull('deleted_at')->value('id')
                ?? DB::table('districts')->value('id');
            $labId         = DB::table('laboratories')->where('name', 'Centreal lab Peshawar')->whereNull('deleted_at')->value('id')
                ?? DB::table('laboratories')->value('id');
            $designationId = DB::table('designations')->value('id');

            // Default hierarchy scope all users inherit (so any controller that
            // reads user.region_id / circle_id / phed_division_id / district_id
            // gets a valid value, regardless of role).
            $baseScope = [
                'region_id'        => $regionId,
                'circle_id'        => $circleId,
                'phed_division_id' => $phedDivId,
                'district_id'      => $districtId,
            ];

            // Lab-incharge sits on the Abbottabad hub so we can test lab
            // catchment routing distinct from the Peshawar default slice.
            $abbRegionId   = DB::table('regions')->where('name', 'Chief Engineer (East)')->value('id');
            $abbCircleId   = DB::table('circles')->where('name', 'SE Abbottabad')->value('id');
            $abbPhedDivId  = DB::table('phed_divisions')->where('name', 'Abbottabad')->value('id');
            $abbDistrictId = DB::table('districts')->where('name', 'Abbottabad')->whereNull('deleted_at')->value('id');
            $abbLabId      = DB::table('laboratories')->where('name', 'Abbottabad Hub lab')->whereNull('deleted_at')->value('id');

            $abbottabadScope = array_filter([
                'region_id'        => $abbRegionId,
                'circle_id'        => $abbCircleId,
                'phed_division_id' => $abbPhedDivId,
                'district_id'      => $abbDistrictId,
            ]);

            // Legacy email rename — the Abbottabad lab-incharge originally
            // shipped as `labincharge.test@mis.com` (misleading, since the
            // .test naming implies the default Peshawar slice). Rename in
            // place so updateOrCreate below updates the same row rather than
            // creating a duplicate. Idempotent — no-op once renamed.
            DB::table('users')
                ->where('email', 'labincharge.test@mis.com')
                ->update(['email' => 'labincharge.abbottabad@mis.com']);

            // Each entry: [role-slug, email, role-specific overrides/flags, lab-override-id|null]
            $testUsers = [
                ['system-administrator',     'admin.test@mis.com',        [], null],
                ['system-manager',           'manager.test@mis.com',      [], null],
                ['view-only-admin',          'viewonly.test@mis.com',     ['is_view_only' => true], null],
                ['general-view-account',     'generalview.test@mis.com',  ['is_view_only' => true, 'allowed_modules' => ['water-samples', 'reports']], null],
                ['chief-engineer',           'ce.test@mis.com',           [], null],
                ['superintending-engineer',  'se.test@mis.com',           [], null],
                ['xen',                      'xen.test@mis.com',          [], null],
                // Abbottabad-hub XEN — closes the upstream catchment so unfit-
                // sample notifications and retest requests for samples
                // registered by clerk.abbottabad have a real recipient.
                ['xen',                      'xen.abbottabad@mis.com',    $abbottabadScope, $abbLabId],
                ['lab-incharge',             'labincharge.abbottabad@mis.com', $abbottabadScope, $abbLabId],
                // Central Lab Peshawar lab-incharge — distinct test user so the
                // asset / material catalogue CRUD perms (granted per-user to
                // central-lab users only) can be exercised end-to-end.
                ['lab-incharge',             'labincharge.central@mis.com', [], $labId],
                ['junior-clerk',             'clerk.test@mis.com',        [], null],
                // Abbottabad-hub junior-clerk — for testing sample registration
                // and catchment scoping outside the default Peshawar slice.
                ['junior-clerk',             'clerk.abbottabad@mis.com',  $abbottabadScope, $abbLabId],
                ['laboratory-assistant',     'labassistant.test@mis.com', [], null],
                // Abbottabad-hub laboratory-assistant — pairs with the
                // Abbottabad clerk so the registration→analysis handoff can
                // be exercised at the Abbottabad lab.
                ['laboratory-assistant',     'labassistant.abbottabad@mis.com', $abbottabadScope, $abbLabId],
            ];

            $lab = $labId ? Laboratory::find($labId) : null;

            foreach ($testUsers as [$roleName, $email, $extras, $labOverrideId]) {
                $role = Role::query()->where('name', $roleName)->first();
                if (!$role) {
                    info("RbacTestUsersSeeder: role '{$roleName}' not found, skipping");
                    continue;
                }

                $userData = array_merge([
                    'name'            => ucwords(str_replace('-', ' ', $roleName)) . ' (Test)',
                    'password'        => Hash::make('Test+Rbac1=2'),
                    'is_active'       => true,
                    'employee_status' => 'active',
                    'designation_id'  => $designationId,
                ], $baseScope, $extras);

                if (isset($userData['allowed_modules']) && is_array($userData['allowed_modules'])) {
                    $userData['allowed_modules'] = json_encode($userData['allowed_modules']);
                }

                $user = User::query()->withoutGlobalScopes()->updateOrCreate(
                    ['email' => $email],
                    $userData
                );

                $user->syncRoles([$roleName]);

                // Attach user to their assigned lab via the laboratory_user pivot.
                // Most roles share the default Peshawar lab; lab-incharge gets a
                // distinct hub (Abbottabad) so catchment routing can be tested.
                // We detach old pivot rows when the user is being repinned to a
                // new lab so stale entries don't accumulate.
                $targetLab = $labOverrideId
                    ? Laboratory::find($labOverrideId)
                    : $lab;

                if ($targetLab) {
                    $user->laboratories()->sync([
                        $targetLab->id => ['present_duty' => $user->name],
                    ]);
                }
            }

            // ── Client test user (clients table — separate path) ──
            $testClientEmail = 'client.test@mis.com';
            if (class_exists(Client::class)) {
                Client::query()->updateOrCreate(
                    ['email' => $testClientEmail],
                    [
                        'name'              => 'Client Test',
                        'phone'             => '03000000000',
                        'address'           => 'Test Client Address',
                        'organization_name' => 'Test Org',
                        'password'          => Hash::make('Test+Rbac1=2'),
                    ]
                );
            }

            // ── Dummy account (silent-success mode; SA role + is_dummy=true) ──
            $dummyRole = Role::query()->where('name', 'system-administrator')->first();
            if ($dummyRole) {
                $dummy = User::query()->withoutGlobalScopes()->updateOrCreate(
                    ['email' => 'dummy.test@mis.com'],
                    array_merge([
                        'name'            => 'Dummy Account (Test)',
                        'password'        => Hash::make('Test+Rbac1=2'),
                        'is_active'       => true,
                        'is_dummy'        => true,
                        'employee_status' => 'active',
                        'designation_id'  => $designationId,
                    ], $baseScope)
                );
                $dummy->syncRoles(['system-administrator']);
                if ($lab) {
                    $dummy->laboratories()->syncWithoutDetaching([
                        $lab->id => ['present_duty' => $dummy->name],
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            info('RbacTestUsersSeeder error: '.$e->getMessage());
            throw $e;
        }
    }
}
