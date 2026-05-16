<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the PHE hierarchy from PHE Heirarchy (3).xlsx (saved as JSON in
 * database/seeders/data/phe_hierarchy.json).
 *
 *   Region (CE)
 *     └─ Admin Division ←→ HUB Lab (a lab can serve multiple circles)
 *          └─ PHE Circle (SE) ── laboratory_id
 *               └─ District
 *                    └─ PHED Division (XEN)
 *                         └─ Sub Division
 *
 * Strategy:
 *   - Idempotent. Existing rows are matched by name (case-insensitive, with
 *     alias map for legacy names) and UPDATED in place — ids stay stable so
 *     existing FKs on water_samples / users / laboratory_user remain valid.
 *   - Missing rows are inserted via firstOrCreate.
 *   - Junk seed rows (e.g. legacy "Alycia Walter" lab) are left alone, not
 *     deleted, so any FK that points at them keeps working.
 */
class PheHierarchySeeder extends Seeder
{
    /**
     * Aliases for matching existing legacy DB names → xlsx canonical names.
     * Keyed by xlsx canonical, value is a list of legacy names that should
     * be normalized to it.
     */
    private const REGION_ALIASES = [
        'Chief Engineer Center'  => ['CE — Centre', 'CE - Centre', 'CE Centre'],
        'Chief Engineer (East)'  => ['CE — East', 'CE East'],
        'Chief Engineer (North)' => ['CE — North', 'CE North'],
        'Chief Engineer (South)' => ['CE — South', 'CE South'],
    ];

    private const DIVISION_ALIASES = [
        'Hazara'  => ['Abbottabad'],
        'DI Khan' => ['D.I. Khan', 'D.I.Khan'],
    ];

    private const LAB_ALIASES = [
        'Centreal lab Peshawar' => ['Central Laboratory Peshawar'],
        'Mardan Hub Lab'        => ['Mardan Laboratory'],
        'Abbottabad Hub lab'    => ['Abbottabad Laboratory'],
        'Batkhela Lab'          => ['Timergara (at Batkhela) Laboratory'],
        'Swat Hub lab'          => ['Swat Laboratory'],
        'Bannu Hub lab'         => ['Bannu/lakki Laboratory'],
        'DI Khan Hub Lab'       => ['Di Khan Laboratory'],
        'Kohat Hub Lab'         => ['Kohat Laboratory'],
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/phe_hierarchy.json');
        if (!file_exists($path)) {
            info("PheHierarchySeeder: JSON not found at {$path}");
            return;
        }

        $json = file_get_contents($path);
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);  // strip UTF-8 BOM
        $rows = json_decode($json, true);
        if (!is_array($rows) || empty($rows)) {
            info('PheHierarchySeeder: JSON empty or invalid');
            return;
        }

        // Drop xlsx header row if present (region == 'Region')
        if (isset($rows[0]['region']) && $rows[0]['region'] === 'Region') {
            array_shift($rows);
        }

        DB::beginTransaction();
        try {
            // Phase 1 — extract unique names for each level
            $uRegions = []; $uDivs = []; $uLabs = []; $uCircles = []; $uDistricts = []; $uPDs = []; $uSubs = [];
            $divToRegion = [];  // admin division → region (each div belongs to exactly one region per xlsx)
            foreach ($rows as $r) {
                $uRegions[$r['region']]               = true;
                $uDivs[$r['admin_division']]          = true;
                $divToRegion[$r['admin_division']]    = $r['region'];
                $uLabs[$r['hub_lab']]                 = $r['admin_division'];
                $uCircles[$r['phe_circle']]           = [$r['region'], $r['hub_lab']];
                $uDistricts[$r['district']]           = [$r['admin_division'], $r['phe_circle']];
                $uPDs[$r['phed_division']]            = [$r['district'], $r['phe_circle']];
                $uSubs[]                              = [$r['sub_division'], $r['phed_division']];
            }

            // Phase 2 — regions (canonical name = xlsx name)
            $regionIds = [];
            foreach (array_keys($uRegions) as $name) {
                $regionIds[$name] = $this->upsertByAlias('regions', $name, self::REGION_ALIASES);
            }

            // Phase 3 — admin divisions (with region_id so cascade filters work)
            $divIds = [];
            foreach (array_keys($uDivs) as $name) {
                $regionName = $divToRegion[$name] ?? null;
                $extra = $regionName && isset($regionIds[$regionName])
                    ? ['region_id' => $regionIds[$regionName]]
                    : [];
                $divIds[$name] = $this->upsertByAlias('divisions', $name, self::DIVISION_ALIASES, $extra);
            }

            // Phase 4 — labs (with division_id)
            $labIds = [];
            foreach ($uLabs as $labName => $adminDivName) {
                $labIds[$labName] = $this->upsertByAlias('laboratories', $labName, self::LAB_ALIASES, [
                    'division_id' => $divIds[$adminDivName] ?? null,
                ]);
            }

            // Phase 5 — PHE circles (with region_id + laboratory_id)
            $circleIds = [];
            foreach ($uCircles as $circleName => [$regionName, $labName]) {
                $circleIds[$circleName] = $this->upsertByAlias('circles', $circleName, [], [
                    'region_id'     => $regionIds[$regionName] ?? null,
                    'laboratory_id' => $labIds[$labName] ?? null,
                ]);
            }

            // Phase 6 — districts (with division_id + circle_id)
            $districtIds = [];
            foreach ($uDistricts as $districtName => [$adminDivName, $circleName]) {
                $districtIds[$districtName] = $this->upsertByAlias('districts', $districtName, [], [
                    'division_id' => $divIds[$adminDivName] ?? null,
                    'circle_id'   => $circleIds[$circleName] ?? null,
                ]);
            }

            // Phase 7 — phed_divisions (with district_id + circle_id)
            $pdIds = [];
            foreach ($uPDs as $pdName => [$districtName, $circleName]) {
                $pdIds[$pdName] = $this->upsertByAlias('phed_divisions', $pdName, [], [
                    'district_id' => $districtIds[$districtName] ?? null,
                    'circle_id'   => $circleIds[$circleName] ?? null,
                ]);
            }

            // Phase 8 — sub_divisions (composite key: phed_division_id + name)
            foreach ($uSubs as [$subName, $pdName]) {
                $pdId = $pdIds[$pdName] ?? null;
                if (!$pdId) continue;
                DB::table('sub_divisions')->updateOrInsert(
                    ['phed_division_id' => $pdId, 'name' => $subName],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            info('PheHierarchySeeder error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upsert by canonical xlsx name, with fallback to alias list. Updates the
     * existing row's name + FKs in place if matched; otherwise inserts.
     * Returns the row id.
     */
    private function upsertByAlias(string $table, string $canonical, array $aliases, array $extra = []): int
    {
        $candidates = [$canonical];
        if (isset($aliases[$canonical])) {
            $candidates = array_merge($candidates, $aliases[$canonical]);
        }

        // soft-delete-aware row lookup
        $hasSoftDeletes = DB::getSchemaBuilder()->hasColumn($table, 'deleted_at');
        $base = DB::table($table);
        if ($hasSoftDeletes) $base->whereNull('deleted_at');

        // First try exact-match (case-insensitive) on any candidate
        $row = (clone $base)->whereIn(DB::raw('LOWER(name)'), array_map('strtolower', $candidates))->first();

        if ($row) {
            $update = array_merge(['name' => $canonical, 'updated_at' => now()], $extra);
            DB::table($table)->where('id', $row->id)->update($update);
            return (int) $row->id;
        }

        $insert = array_merge(['name' => $canonical, 'created_at' => now(), 'updated_at' => now()], $extra);
        return (int) DB::table($table)->insertGetId($insert);
    }
}
