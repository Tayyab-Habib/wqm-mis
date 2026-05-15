<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Centralised query-scoping for RBAC.
 *
 * Each method takes a query builder + an authenticated user, applies the
 * right where-clauses based on the user's role, and returns the (modified)
 * builder so it can be chained.
 *
 * Roles that BYPASS scoping (see everything):
 *   - system-administrator
 *   - system-manager
 *   - view-only-admin (writes are blocked elsewhere)
 *   - general-view-account (allowed_modules controls visibility at app layer)
 *
 * Hierarchy-scoped roles:
 *   - chief-engineer            → region_id
 *   - superintending-engineer   → circle_id
 *   - xen                       → phed_division_id
 *
 * Lab-scoped roles (use the laboratory_user pivot — multi-lab capable):
 *   - lab-incharge
 *   - junior-clerk
 *   - laboratory-assistant
 *
 * Each scope method documents which column on the target table it filters by.
 * Callers must pass a query against the right base table.
 */
class AuthScope
{
    /**
     * Roles that should see everything (no filter applied).
     */
    public const UNSCOPED_ROLES = [
        'system-administrator',
        'system-manager',
        'view-only-admin',
        'general-view-account',
    ];

    /**
     * Pick the highest-priority role on the user, in case they have several.
     * Returns null if the user has no recognised role.
     */
    public static function primaryRole(?User $user): ?string
    {
        if (!$user) return null;
        $roles = $user->getRoleNames()->toArray();
        // Priority order — most permissive first
        $priority = [
            'system-administrator',
            'view-only-admin',
            'system-manager',
            'general-view-account',
            'chief-engineer',
            'superintending-engineer',
            'xen',
            'lab-incharge',
            'junior-clerk',
            'laboratory-assistant',
        ];
        foreach ($priority as $role) {
            if (in_array($role, $roles, true)) return $role;
        }
        return $roles[0] ?? null;
    }

    /**
     * Returns the laboratory_ids this user has access to (from the existing
     * laboratory_user pivot). Used by lab-scoped roles.
     *
     * @return array<int>
     */
    public static function userLabIds(User $user): array
    {
        return $user->laboratories()->pluck('laboratories.id')->toArray();
    }

    /**
     * Returns the table name backing a query builder. Works for both Eloquent
     * Builder (has getModel) and raw Query\Builder (has from). Falls back to
     * the supplied $default if neither is detectable.
     */
    private static function tableFor($query, string $default): string
    {
        if (method_exists($query, 'getModel') && $query->getModel()) {
            return $query->getModel()->getTable();
        }
        if (property_exists($query, 'from') && $query->from) {
            // strip alias if present ("water_samples as ws" → "water_samples")
            return explode(' ', $query->from)[0];
        }
        return $default;
    }

    /**
     * Scope a water_samples query by the user's role.
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function waterSamples($query, ?User $user)
    {
        $role = self::primaryRole($user);
        if (!$user || !$role || in_array($role, self::UNSCOPED_ROLES, true)) {
            return $query;
        }

        $table = self::tableFor($query, 'water_samples');

        switch ($role) {
            case 'chief-engineer':
                return $query->where("{$table}.region_id", $user->region_id);
            case 'superintending-engineer':
                return $query->where("{$table}.circle_id", $user->circle_id);
            case 'xen':
                return $query->where("{$table}.phed_division_id", $user->phed_division_id);
            case 'lab-incharge':
            case 'junior-clerk':
            case 'laboratory-assistant':
                $labIds = self::userLabIds($user);
                if (empty($labIds)) {
                    // No lab assigned → see nothing (safer than seeing everything)
                    return $query->whereRaw('1 = 0');
                }
                return $query->whereIn("{$table}.laboratory_id", $labIds);
            default:
                // Unknown role → no data
                return $query->whereRaw('1 = 0');
        }
    }

    /**
     * Scope a materials/laboratory_materials query.
     * Materials live per-lab on laboratory_materials; the master `materials`
     * table is global. For hierarchical (CE/SE/XEN) roles we don't filter
     * materials (they see all stock for now); for lab roles we filter to
     * their lab(s) on the laboratory_materials pivot.
     */
    public static function laboratoryMaterials($query, ?User $user)
    {
        $role = self::primaryRole($user);
        if (!$user || !$role || in_array($role, self::UNSCOPED_ROLES, true)) {
            return $query;
        }
        if (in_array($role, ['chief-engineer', 'superintending-engineer', 'xen'], true)) {
            return $query;
        }

        $labIds = self::userLabIds($user);
        if (empty($labIds)) return $query->whereRaw('1 = 0');
        $table = self::tableFor($query, 'laboratory_materials');
        return $query->whereIn("{$table}.laboratory_id", $labIds);
    }

    /**
     * Scope a laboratory_assets / equipment query.
     */
    public static function laboratoryAssets($query, ?User $user)
    {
        $role = self::primaryRole($user);
        if (!$user || !$role || in_array($role, self::UNSCOPED_ROLES, true)) {
            return $query;
        }
        if (in_array($role, ['chief-engineer', 'superintending-engineer', 'xen'], true)) {
            return $query;
        }

        $labIds = self::userLabIds($user);
        if (empty($labIds)) return $query->whereRaw('1 = 0');
        $table = self::tableFor($query, 'laboratory_assets');
        return $query->whereIn("{$table}.laboratory_id", $labIds);
    }

    /**
     * Scope an inventories (demand) query.
     * Lab roles see only demands raised BY their lab or addressed TO their lab.
     */
    public static function inventories($query, ?User $user)
    {
        $role = self::primaryRole($user);
        if (!$user || !$role || in_array($role, self::UNSCOPED_ROLES, true)) {
            return $query;
        }
        if (in_array($role, ['chief-engineer', 'superintending-engineer', 'xen'], true)) {
            return $query;
        }

        $labIds = self::userLabIds($user);
        if (empty($labIds)) return $query->whereRaw('1 = 0');
        $table = self::tableFor($query, 'inventories');
        return $query->where(function ($q) use ($table, $labIds) {
            $q->whereIn("{$table}.laboratory_id", $labIds)
              ->orWhereIn("{$table}.recipient_lab_id", $labIds);
        });
    }

    /**
     * Should the user be allowed to perform a write request? Used by the
     * EnforceViewOnlyMiddleware. Returns false ONLY for view-only roles or
     * users with is_view_only=true.
     */
    public static function canWrite(?User $user): bool
    {
        if (!$user) return false;
        if ($user->is_view_only) return false;
        if ($user->hasRole('view-only-admin')) return false;
        return true;
    }

    // ════════════════════════════════════════════════════════════════════
    // Hierarchy ID resolvers — compute the set of ids at each hierarchy
    // level that the user is allowed to see. These power both the dropdown
    // scoping below AND any callers that need to know "which districts can
    // this user touch?" (e.g. write-validation, dashboard joins).
    //
    // All return arrays of integers. Empty array = user sees nothing at
    // that level (safer than seeing everything by mistake).
    // ════════════════════════════════════════════════════════════════════

    /** All region_ids the user can see. */
    public static function userRegionIds(?User $user): array
    {
        if (!$user) return [];
        $role = self::primaryRole($user);
        if (in_array($role, self::UNSCOPED_ROLES, true)) {
            return DB::table('regions')->pluck('id')->all();
        }
        if ($role === 'chief-engineer') {
            return $user->region_id ? [(int) $user->region_id] : [];
        }
        if ($role === 'superintending-engineer') {
            return $user->circle_id
                ? DB::table('circles')->where('id', $user->circle_id)->pluck('region_id')->filter()->unique()->values()->all()
                : [];
        }
        if ($role === 'xen') {
            return $user->phed_division_id
                ? DB::table('phed_divisions')
                    ->join('circles', 'phed_divisions.circle_id', '=', 'circles.id')
                    ->where('phed_divisions.id', $user->phed_division_id)
                    ->pluck('circles.region_id')->filter()->unique()->values()->all()
                : [];
        }
        if (in_array($role, ['lab-incharge', 'junior-clerk', 'laboratory-assistant'], true)) {
            $labIds = self::userLabIds($user);
            if (empty($labIds)) return [];
            return DB::table('circles')->whereIn('laboratory_id', $labIds)
                ->pluck('region_id')->filter()->unique()->values()->all();
        }
        return [];
    }

    /** All circle_ids the user can see. */
    public static function userCircleIds(?User $user): array
    {
        if (!$user) return [];
        $role = self::primaryRole($user);
        if (in_array($role, self::UNSCOPED_ROLES, true)) {
            return DB::table('circles')->pluck('id')->all();
        }
        if ($role === 'chief-engineer') {
            return $user->region_id
                ? DB::table('circles')->where('region_id', $user->region_id)->pluck('id')->all()
                : [];
        }
        if ($role === 'superintending-engineer') {
            return $user->circle_id ? [(int) $user->circle_id] : [];
        }
        if ($role === 'xen') {
            return $user->phed_division_id
                ? DB::table('phed_divisions')->where('id', $user->phed_division_id)->pluck('circle_id')->filter()->unique()->values()->all()
                : [];
        }
        if (in_array($role, ['lab-incharge', 'junior-clerk', 'laboratory-assistant'], true)) {
            $labIds = self::userLabIds($user);
            if (empty($labIds)) return [];
            return DB::table('circles')->whereIn('laboratory_id', $labIds)->pluck('id')->all();
        }
        return [];
    }

    /** All admin division_ids the user can see (Administrative Divisions, NOT PHED Divisions). */
    public static function userDivisionIds(?User $user): array
    {
        if (!$user) return [];
        $role = self::primaryRole($user);
        if (in_array($role, self::UNSCOPED_ROLES, true)) {
            return DB::table('divisions')->pluck('id')->all();
        }
        // For all scoped roles: derive admin divisions from the districts
        // they can see. districts.division_id is the source of truth.
        $districtIds = self::userDistrictIds($user);
        if (empty($districtIds)) return [];
        return DB::table('districts')->whereIn('id', $districtIds)
            ->pluck('division_id')->filter()->unique()->values()->all();
    }

    /** All district_ids the user can see (lab roles apply catchment via lab.division_id). */
    public static function userDistrictIds(?User $user): array
    {
        if (!$user) return [];
        $role = self::primaryRole($user);
        if (in_array($role, self::UNSCOPED_ROLES, true)) {
            return DB::table('districts')->whereNull('deleted_at')->pluck('id')->all();
        }
        if ($role === 'chief-engineer') {
            // All districts in their region's circles
            $circleIds = self::userCircleIds($user);
            if (empty($circleIds)) return [];
            return DB::table('districts')->whereIn('circle_id', $circleIds)->whereNull('deleted_at')->pluck('id')->all();
        }
        if ($role === 'superintending-engineer') {
            return $user->circle_id
                ? DB::table('districts')->where('circle_id', $user->circle_id)->whereNull('deleted_at')->pluck('id')->all()
                : [];
        }
        if ($role === 'xen') {
            return $user->phed_division_id
                ? DB::table('phed_divisions')->where('id', $user->phed_division_id)->pluck('district_id')->filter()->unique()->values()->all()
                : [];
        }
        if (in_array($role, ['lab-incharge', 'junior-clerk', 'laboratory-assistant'], true)) {
            // Lab catchment: all districts in the lab's admin division(s)
            $labIds = self::userLabIds($user);
            if (empty($labIds)) return [];
            $divisionIds = DB::table('laboratories')->whereIn('id', $labIds)->pluck('division_id')->filter()->unique()->all();
            if (empty($divisionIds)) return [];
            return DB::table('districts')->whereIn('division_id', $divisionIds)->whereNull('deleted_at')->pluck('id')->all();
        }
        return [];
    }

    /** All phed_division_ids the user can see. */
    public static function userPhedDivisionIds(?User $user): array
    {
        if (!$user) return [];
        $role = self::primaryRole($user);
        if (in_array($role, self::UNSCOPED_ROLES, true)) {
            return DB::table('phed_divisions')->pluck('id')->all();
        }
        if ($role === 'xen') {
            return $user->phed_division_id ? [(int) $user->phed_division_id] : [];
        }
        // CE/SE/Lab: PDs whose district is in user's district scope
        $districtIds = self::userDistrictIds($user);
        if (empty($districtIds)) return [];
        return DB::table('phed_divisions')->whereIn('district_id', $districtIds)->pluck('id')->all();
    }

    /** All laboratory_ids the user can see. */
    public static function visibleLabIds(?User $user): array
    {
        if (!$user) return [];
        $role = self::primaryRole($user);
        if (in_array($role, self::UNSCOPED_ROLES, true)) {
            return DB::table('laboratories')->whereNull('deleted_at')->pluck('id')->all();
        }
        if (in_array($role, ['lab-incharge', 'junior-clerk', 'laboratory-assistant'], true)) {
            return self::userLabIds($user);
        }
        // CE/SE/XEN: labs in their circle scope (via circles.laboratory_id)
        $circleIds = self::userCircleIds($user);
        if (empty($circleIds)) return [];
        return DB::table('circles')->whereIn('id', $circleIds)
            ->whereNotNull('laboratory_id')->pluck('laboratory_id')->unique()->values()->all();
    }

    // ════════════════════════════════════════════════════════════════════
    // Dropdown scoping methods — apply user's hierarchy ids as a where-in
    // on the matching table. Each takes a query builder + user and returns
    // the modified builder (chainable).
    // ════════════════════════════════════════════════════════════════════

    public static function regions($query, ?User $user)
    {
        $ids = self::userRegionIds($user);
        $table = self::tableFor($query, 'regions');
        if (empty($ids)) return $query->whereRaw('1 = 0');
        return $query->whereIn("{$table}.id", $ids);
    }

    public static function divisions($query, ?User $user)
    {
        $ids = self::userDivisionIds($user);
        $table = self::tableFor($query, 'divisions');
        if (empty($ids)) return $query->whereRaw('1 = 0');
        return $query->whereIn("{$table}.id", $ids);
    }

    public static function circles($query, ?User $user)
    {
        $ids = self::userCircleIds($user);
        $table = self::tableFor($query, 'circles');
        if (empty($ids)) return $query->whereRaw('1 = 0');
        return $query->whereIn("{$table}.id", $ids);
    }

    public static function districts($query, ?User $user)
    {
        $ids = self::userDistrictIds($user);
        $table = self::tableFor($query, 'districts');
        if (empty($ids)) return $query->whereRaw('1 = 0');
        return $query->whereIn("{$table}.id", $ids);
    }

    public static function phedDivisions($query, ?User $user)
    {
        $ids = self::userPhedDivisionIds($user);
        $table = self::tableFor($query, 'phed_divisions');
        if (empty($ids)) return $query->whereRaw('1 = 0');
        return $query->whereIn("{$table}.id", $ids);
    }

    public static function labs($query, ?User $user)
    {
        $ids = self::visibleLabIds($user);
        $table = self::tableFor($query, 'laboratories');
        if (empty($ids)) return $query->whereRaw('1 = 0');
        return $query->whereIn("{$table}.id", $ids);
    }

    // ════════════════════════════════════════════════════════════════════
    // Entity scoping — for tables that have a laboratory_id but no direct
    // region/circle/phed_division columns (payments, complaints, diaries,
    // water_schemes). Filter by the visible lab ids.
    // ════════════════════════════════════════════════════════════════════

    public static function waterSchemes($query, ?User $user)
    {
        $role = self::primaryRole($user);
        if (!$user || !$role || in_array($role, self::UNSCOPED_ROLES, true)) return $query;
        $districtIds = self::userDistrictIds($user);
        if (empty($districtIds)) return $query->whereRaw('1 = 0');
        $table = self::tableFor($query, 'water_schemes');
        return $query->whereIn("{$table}.district_id", $districtIds);
    }

    /**
     * Lab-attached entity (payments, complaints, diaries, dispatches, etc.).
     * For hierarchy roles, filter to labs in their hierarchy.
     */
    public static function laboratoryScoped($query, ?User $user, string $defaultTable, string $column = 'laboratory_id')
    {
        $role = self::primaryRole($user);
        if (!$user || !$role || in_array($role, self::UNSCOPED_ROLES, true)) return $query;
        $labIds = self::visibleLabIds($user);
        if (empty($labIds)) return $query->whereRaw('1 = 0');
        $table = self::tableFor($query, $defaultTable);
        return $query->whereIn("{$table}.{$column}", $labIds);
    }

    public static function payments($query, ?User $user)
    {
        return self::laboratoryScoped($query, $user, 'payments');
    }

    public static function complaints($query, ?User $user)
    {
        return self::laboratoryScoped($query, $user, 'complaints');
    }

    public static function diaries($query, ?User $user)
    {
        return self::laboratoryScoped($query, $user, 'diaries');
    }

    public static function dispatches($query, ?User $user)
    {
        return self::laboratoryScoped($query, $user, 'dispatches');
    }
}
