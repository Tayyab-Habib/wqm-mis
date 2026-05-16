<?php

namespace App\Http\Controllers\Ce;

use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\District;
use App\Models\PhedDivision;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleTest;
use App\Models\WaterScheme;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Chief Engineer (CE) portal — region-scoped oversight.
 *
 * A CE oversees a Region, which contains multiple SE Circles, which contain
 * PHE Divisions (XEN scope). All endpoints here filter samples by the
 * CE's region_id (via circles → phed_divisions) so a CE only sees their area.
 */
class CePortalController extends Controller
{
    /* ──────────────────────────────────────────────────────────────────
     |  Scope helpers
     |──────────────────────────────────────────────────────────────────*/

    /** @return int[] phed_division ids under the current CE's region (empty = unrestricted) */
    private function phedDivisionIds(): array
    {
        $user = auth()->user();
        if (!$user || !$user->region_id) return [];
        return PhedDivision::query()
            ->whereHas('circle', fn ($q) => $q->where('region_id', $user->region_id))
            ->pluck('id')
            ->all();
    }

    /** @return int[] circle ids under the current CE's region (empty = unrestricted) */
    private function circleIds(): array
    {
        $user = auth()->user();
        if (!$user || !$user->region_id) return [];
        return Circle::query()
            ->where('region_id', $user->region_id)
            ->pluck('id')
            ->all();
    }

    /** Adds the region scope to a samples query (no-op if user has no region). */
    private function scopeSamples($q)
    {
        $ids = $this->phedDivisionIds();
        if (!empty($ids)) $q->whereIn('water_samples.phed_division_id', $ids);
        return $q;
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/me  — identity for layout
     |──────────────────────────────────────────────────────────────────*/
    public function me(): JsonResponse
    {
        $user = auth()->user()->load(['region', 'circle', 'district', 'designation']);

        $circles = Circle::query()
            ->when($user->region_id, fn ($q) => $q->where('region_id', $user->region_id))
            ->orderBy('name')
            ->get(['id', 'name', 'region_id']);

        return response()->json([
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone,
            'designation'  => $user->designation?->name ?? 'Chief Engineer',
            'region'       => $user->region ? ['id' => $user->region->id, 'name' => $user->region->name] : null,
            'circles'      => $circles->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'label' => 'SE ' . $c->name . ' Circle']),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/dashboard
     |──────────────────────────────────────────────────────────────────*/
    public function dashboard(): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();

        // ── Row 1: WSS + sample counters ─────────────────────────────
        $schemes = WaterScheme::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->select(DB::raw('
                COUNT(*) as total,
                SUM(CASE WHEN LOWER(power_input) LIKE "%solar%" THEN 1 ELSE 0 END) as solar,
                SUM(CASE WHEN LOWER(power_input) NOT LIKE "%solar%" OR power_input IS NULL THEN 1 ELSE 0 END) as non_solar
            '))
            ->first();

        $testedWss = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereNotNull('water_scheme_id')
            ->distinct('water_scheme_id')
            ->count('water_scheme_id');

        $sampleCounts = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereNotNull('current_status')
            ->select(DB::raw('
                COUNT(*) as total,
                SUM(CASE WHEN current_status = ' . WaterSampleCurrentStatusEnum::FIT->value . ' THEN 1 ELSE 0 END) as fit,
                SUM(CASE WHEN current_status = ' . WaterSampleCurrentStatusEnum::UNFIT->value . ' THEN 1 ELSE 0 END) as unfit
            '))
            ->first();

        $tested = (int) ($sampleCounts->total ?? 0);
        $fit    = (int) ($sampleCounts->fit ?? 0);
        $unfit  = (int) ($sampleCounts->unfit ?? 0);

        // Unfit follow-up = unfit samples grouped by outcome of latest action
        $unfitFollowup = $this->buildUnfitFollowup($phedIds);

        // ── Row 2: escalations & compliance ──────────────────────────
        $escalations = $this->buildEscalations($phedIds);
        $persistent  = $this->buildPersistentUnfit($phedIds, limit: 3);

        // ── SE-wise WQ summary table ─────────────────────────────────
        $seSummary = $this->buildSeSummary();

        // ── CE Escalated — Action Required ───────────────────────────
        $ceEscalated = $this->buildCeEscalatedList($phedIds, limit: 10);

        // ── Notifications (last 7 days) ──────────────────────────────
        $notifs = $this->buildNotifications($phedIds, days: 7, limit: 8);

        return response()->json([
            'scope' => [
                'ce_name'      => $user->name,
                'region'       => $user->region?->name ?? 'N/A',
                'circles'      => Circle::where('region_id', $user->region_id)->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [
                    'id'    => $c->id,
                    'name'  => $c->name,
                    'label' => 'SE ' . $c->name . ' Circle',
                ]),
            ],
            'row1' => [
                'functional_wss'  => [
                    'total'     => (int) ($schemes->total ?? 0),
                    'solar'     => (int) ($schemes->solar ?? 0),
                    'non_solar' => (int) ($schemes->non_solar ?? 0),
                ],
                'tested_wss'      => $testedWss,
                'tested_samples'  => $tested,
                'fit_samples'     => $fit,
                'unfit_samples'   => $unfit,
                'unfit_followup'  => $unfitFollowup,
            ],
            'row2' => $escalations,
            'se_summary'   => $seSummary,
            'persistent'   => $persistent,
            'ce_escalated' => $ceEscalated,
            'notifications'=> $notifs,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/circles/{circleId}  — SE Circle detail
     |──────────────────────────────────────────────────────────────────*/
    public function circleDetail(int $circleId): JsonResponse
    {
        $user = auth()->user();
        $circle = Circle::query()
            ->when($user->region_id, fn ($q) => $q->where('region_id', $user->region_id))
            ->findOrFail($circleId);

        $phedIds = PhedDivision::where('circle_id', $circleId)->pluck('id')->all();

        // SE name — placeholder (no se_user_id linkage yet); use first SE-roled user in that circle if any
        $seUser = \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'se'))
            ->where('circle_id', $circleId)
            ->first();
        $districts = District::where('circle_id', $circleId)->pluck('name')->all();

        // KPI cards
        $noAction = $this->countUnfitWithStage($phedIds, stage: 'no_action');
        $seEsc    = $this->countUnfitWithStage($phedIds, stage: 'se_escalated');
        $ceEsc    = $this->countUnfitWithStage($phedIds, stage: 'ce_escalated');
        $resolvedThisYear = $this->countResolvedThisYear($phedIds);

        // Unfit Samples table grouped by district
        $samples = $this->buildUnfitSamplesGroupedByDistrict($phedIds);

        return response()->json([
            'circle' => [
                'id'        => $circle->id,
                'name'      => $circle->name,
                'label'     => 'SE ' . $circle->name . ' Circle',
                'se_name'   => $seUser?->name ?? 'Engr. — (vacant)',
                'districts' => $districts,
            ],
            'stats' => [
                'no_action'         => $noAction,
                'se_escalated'      => $seEsc,
                'ce_escalated'      => $ceEsc,
                'resolved_this_year'=> $resolvedThisYear,
            ],
            'samples' => $samples,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/escalated-cases
     |──────────────────────────────────────────────────────────────────*/
    public function escalatedCases(): JsonResponse
    {
        $phedIds = $this->phedDivisionIds();
        $escalated  = $this->buildCeEscalatedList($phedIds, limit: 100);
        $approaching = $this->buildApproachingCeList($phedIds, limit: 100);

        $longestDays = 0;
        $longestSlug = '—';
        foreach ($escalated as $row) {
            if (($row['days_elapsed'] ?? 0) > $longestDays) {
                $longestDays = (int) $row['days_elapsed'];
                $longestSlug = $row['slug'] ?? '—';
            }
        }

        return response()->json([
            'stats' => [
                'ce_active'              => count($escalated),
                'longest_days'           => $longestDays,
                'longest_slug'           => $longestSlug,
                'se_approaching'         => count($approaching),
                'ce_resolved_this_year'  => $this->countResolvedThisYear($phedIds),
            ],
            'escalated'   => $escalated,
            'approaching' => $approaching,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/persistent-unfit
     |──────────────────────────────────────────────────────────────────*/
    public function persistentUnfit(): JsonResponse
    {
        $phedIds = $this->phedDivisionIds();
        $list = $this->buildPersistentUnfit($phedIds, limit: 100);

        return response()->json([
            'stats' => [
                'persistent'         => count($list),
                'escalated_secretary'=> count($list), // every R2-fail row escalates
                'fate_issued'        => $this->countFateIssued($phedIds),
                'remediated_ytd'     => $this->countResolvedThisYear($phedIds),
            ],
            'list' => $list,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/gar  — GAR scoped to CE region with SE-wise abstract
     |──────────────────────────────────────────────────────────────────*/
    public function gar(Request $request): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();

        $fromDate = $request->query('from_date');
        $toDate   = $request->query('to_date');
        $circleId = $request->query('circle_id');
        $districtId = $request->query('district_id');

        if ($circleId) {
            $phedIds = PhedDivision::where('circle_id', $circleId)->pluck('id')->all();
        }
        if ($districtId) {
            $phedIds = PhedDivision::whereIn('id', $phedIds)
                ->where('district_id', $districtId)
                ->pluck('id')->all();
        }

        $sampleQuery = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->when($fromDate, fn ($q) => $q->whereDate('sampled_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('sampled_at', '<=', $toDate));

        $tested = (clone $sampleQuery)->count();
        $fit    = (clone $sampleQuery)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
        $unfit  = (clone $sampleQuery)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();

        $wssTotal = WaterScheme::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->count();
        $wssCovered = (clone $sampleQuery)
            ->whereNotNull('water_scheme_id')
            ->distinct('water_scheme_id')
            ->count('water_scheme_id');

        $labs = DB::table('water_samples as ws')
            ->leftJoin('laboratories as l', 'ws.laboratory_id', '=', 'l.id')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->whereNotNull('ws.laboratory_id')
            ->distinct()
            ->pluck('l.name')
            ->filter()
            ->values();

        // SE-wise abstract (each Circle becomes a row)
        $circleRows = Circle::query()
            ->when($user->region_id, fn ($q) => $q->where('region_id', $user->region_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($c) use ($fromDate, $toDate) {
                $ids = PhedDivision::where('circle_id', $c->id)->pluck('id')->all();
                $q = WaterSample::query()
                    ->whereIn('phed_division_id', $ids)
                    ->when($fromDate, fn ($q2) => $q2->whereDate('sampled_at', '>=', $fromDate))
                    ->when($toDate, fn ($q2) => $q2->whereDate('sampled_at', '<=', $toDate));

                $t = (clone $q)->count();
                $f = (clone $q)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
                $u = (clone $q)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();

                $pctUnfit = $t > 0 ? round(($u / $t) * 100, 1) : 0;
                $pctFit   = $t > 0 ? round(($f / $t) * 100, 1) : 0;
                $rag = $pctUnfit >= 20 ? 'high' : ($pctUnfit >= 10 ? 'medium' : 'low');

                $seUser = \App\Models\User::query()
                    ->whereHas('roles', fn ($q2) => $q2->where('name', 'se'))
                    ->where('circle_id', $c->id)
                    ->first();

                return [
                    'circle_id'  => $c->id,
                    'circle'     => 'SE ' . $c->name . ' Circle',
                    'se_name'    => $seUser?->name ?? '—',
                    'tested'     => $t,
                    'fit'        => $f,
                    'unfit'      => $u,
                    'pct_unfit'  => $pctUnfit,
                    'pct_fit'    => $pctFit,
                    'rag'        => $rag,
                ];
            });

        return response()->json([
            'scope' => [
                'ce_name' => $user->name,
                'region'  => $user->region?->name ?? '—',
                'circles' => $circleRows->pluck('circle'),
            ],
            'kpi' => [
                'total_tested' => $tested,
                'fit'          => $fit,
                'unfit'        => $unfit,
                'pct_unfit'    => $tested > 0 ? round(($unfit / $tested) * 100, 1) : 0,
                'wss_covered'  => $wssCovered,
                'wss_total'    => $wssTotal,
                'lab_count'    => $labs->count(),
                'lab_names'    => $labs->all(),
            ],
            'se_abstract' => $circleRows,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /ce/wss-register
     |──────────────────────────────────────────────────────────────────*/
    public function wssRegister(Request $request): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();
        $circleIds = $this->circleIds();

        $q          = $request->query('q');
        $circleId   = $request->query('circle_id');
        $districtId = $request->query('district_id');
        $result     = $request->query('result');

        $schemes = WaterScheme::query()
            ->with(['district:id,name,circle_id', 'phedDivision:id,name,circle_id'])
            ->when(!empty($phedIds), fn ($qx) => $qx->whereIn('phed_division_id', $phedIds))
            ->when($q, fn ($qx) => $qx->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('address', 'like', "%{$q}%");
            }))
            ->when($circleId, fn ($qx) => $qx->whereHas('phedDivision', fn ($p) => $p->where('circle_id', $circleId)))
            ->when($districtId, fn ($qx) => $qx->where('district_id', $districtId))
            ->orderBy('name')
            ->get();

        // Fold in last-sample info per scheme
        $schemeIds = $schemes->pluck('id');
        $latestSamples = DB::table('water_samples')
            ->whereIn('water_scheme_id', $schemeIds)
            ->whereNull('deleted_at')
            ->select('water_scheme_id', DB::raw('MAX(sampled_at) as last_sampled_at'), DB::raw('COUNT(*) as times_tested'))
            ->groupBy('water_scheme_id')
            ->get()
            ->keyBy('water_scheme_id');

        $resultPerScheme = DB::table('water_samples')
            ->whereIn('water_scheme_id', $schemeIds)
            ->whereNull('deleted_at')
            ->orderBy('water_scheme_id')
            ->orderByDesc('sampled_at')
            ->get(['id', 'water_scheme_id', 'current_status', 'sampled_at', 'result'])
            ->groupBy('water_scheme_id')
            ->map(fn ($rows) => $rows->first());

        $rows = $schemes->map(function ($w) use ($latestSamples, $resultPerScheme) {
            $info = $latestSamples[$w->id] ?? null;
            $last = $resultPerScheme[$w->id] ?? null;
            $result = $this->resolveResult($last);

            $nextScheduled = null;
            $overdue = false;
            if ($info && $info->last_sampled_at) {
                $nextDt = Carbon::parse($info->last_sampled_at)->addMonths(3);
                $nextScheduled = $nextDt->toDateString();
                $overdue = $nextDt->isPast();
            }

            return [
                'id'             => $w->id,
                'wss_code'       => 'WSS-' . str_pad($w->id, 4, '0', STR_PAD_LEFT),
                'wss_name'       => $w->name,
                'district'       => $w->district?->name ?? '—',
                'phed_division'  => $w->phedDivision?->name ?? '—',
                'circle_id'      => $w->phedDivision?->circle_id,
                'source_type'    => $w->source_type ?? 'Tube Well',
                'power_input'    => $w->power_input,
                'operational_status' => $w->is_active ? 'Operational' : 'Inactive',
                'times_tested'   => (int) ($info->times_tested ?? 0),
                'last_result'    => $result,
                'last_sampled_at'=> $info?->last_sampled_at,
                'next_scheduled' => $nextScheduled,
                'overdue'        => $overdue,
                'last_sample_id' => $last?->id,
            ];
        });

        if ($result) $rows = $rows->where('last_result', $result)->values();

        $stats = [
            'total'      => $rows->count(),
            'last_fit'   => $rows->where('last_result', 'Fit')->count(),
            'last_unfit' => $rows->where('last_result', 'Unfit')->count(),
            'untested'   => $rows->where('last_result', 'Untested')->count(),
            'overdue'    => $rows->where('overdue', true)->count(),
        ];

        $circles = Circle::query()
            ->when($user->region_id, fn ($q) => $q->where('region_id', $user->region_id))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => 'SE ' . $c->name . ' Circle']);

        $districts = District::query()
            ->when(!empty($circleIds), fn ($q) => $q->whereIn('circle_id', $circleIds))
            ->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'stats'     => $stats,
            'rows'      => $rows->values(),
            'circles'   => $circles,
            'districts' => $districts,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  Builders / helpers
     |──────────────────────────────────────────────────────────────────*/

    private function buildUnfitFollowup(array $phedIds): array
    {
        // Take all unfit samples (any round had unfit), check if any later test
        // (round > 0) was analyzed and what its result was.
        $unfitSampleIds = WaterSampleTest::query()
            ->join('water_samples as ws', 'water_sample_tests.water_sample_id', '=', 'ws.id')
            ->where('water_sample_tests.result', WaterSampleTestResultEnum::UNFIT->value)
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->distinct()
            ->pluck('water_sample_tests.water_sample_id');

        $fit = 0; $stillUnfit = 0; $closed = 0; $total = 0;
        foreach ($unfitSampleIds as $sid) {
            $total++;
            $latest = WaterSampleTest::where('water_sample_id', $sid)->orderByDesc('round')->first();
            if (!$latest) { continue; }
            $resultVal = $latest->result instanceof \BackedEnum ? $latest->result->value : $latest->result;
            if ($latest->round > 0) {
                if ((int) $resultVal === WaterSampleTestResultEnum::FIT->value) $fit++;
                else if ((int) $resultVal === WaterSampleTestResultEnum::UNFIT->value) $stillUnfit++;
            }
            if (WaterSample::find($sid)?->is_closed) $closed++;
        }

        $rate = $total > 0 ? round((($fit + $stillUnfit + $closed) / $total) * 100, 0) : 0;
        return [
            'total'        => $total,
            'fit'          => $fit,
            'still_unfit'  => $stillUnfit,
            'closed'       => $closed,
            'rate_percent' => $rate,
        ];
    }

    private function buildEscalations(array $phedIds): array
    {
        $unfitNoAction = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->whereNotIn('id', function ($q) { $q->select('water_sample_id')->from('water_sample_actions'); })
            ->count();

        $ceEscalated = $this->countByDaysSinceUnfitNotification($phedIds, minDays: 20);
        $seEscalated = $this->countByDaysSinceUnfitNotification($phedIds, minDays: 10, maxDays: 19);
        $persistent  = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->count();
        $resolved = $this->countResolvedThisYear($phedIds);

        return [
            'ce_escalated_no_action' => $ceEscalated,
            'se_escalated_active'    => $seEscalated,
            'persistent_unfit'       => $persistent,
            'resolved_this_year'     => $resolved,
            'unfit_no_action'        => $unfitNoAction,
        ];
    }

    private function countByDaysSinceUnfitNotification(array $phedIds, int $minDays, ?int $maxDays = null): int
    {
        $q = DB::table('notifications as n')
            ->join('water_samples as ws', 'n.water_sample_id', '=', 'ws.id')
            ->where('n.type_key', 'SAMPLE_UNFIT')
            ->whereNull('n.action_taken_at')
            ->whereNull('ws.deleted_at')
            ->whereNotNull('n.created_at')
            ->when(!empty($phedIds), fn ($q2) => $q2->whereIn('ws.phed_division_id', $phedIds))
            ->whereRaw('DATEDIFF(NOW(), n.created_at) >= ?', [$minDays]);

        if ($maxDays !== null) {
            $q->whereRaw('DATEDIFF(NOW(), n.created_at) <= ?', [$maxDays]);
        }
        return $q->distinct('n.water_sample_id')->count('n.water_sample_id');
    }

    private function countResolvedThisYear(array $phedIds): int
    {
        return WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereYear('updated_at', now()->year)
            ->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)
            ->where('current_round', '>', 0)
            ->count();
    }

    private function countFateIssued(array $phedIds): int
    {
        if (!DB::getSchemaBuilder()->hasColumn('water_samples', 'fate_decision')) return 0;
        return WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereNotNull('fate_decision')
            ->count();
    }

    private function countUnfitWithStage(array $phedIds, string $stage): int
    {
        $q = WaterSample::query()
            ->when(!empty($phedIds), fn ($qx) => $qx->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value);

        if ($stage === 'no_action') {
            return $q->whereNotIn('id', function ($q) { $q->select('water_sample_id')->from('water_sample_actions'); })->count();
        }
        if ($stage === 'se_escalated') {
            // 10-19 days since unfit notification, no action taken
            return $this->countByDaysSinceUnfitNotification($phedIds, 10, 19);
        }
        if ($stage === 'ce_escalated') {
            return $this->countByDaysSinceUnfitNotification($phedIds, 20);
        }
        return 0;
    }

    private function buildSeSummary(): array
    {
        $user = auth()->user();
        $circles = Circle::query()
            ->when($user->region_id, fn ($q) => $q->where('region_id', $user->region_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $circles->map(function ($c) {
            $phedIds = PhedDivision::where('circle_id', $c->id)->pluck('id')->all();
            $districts = District::where('circle_id', $c->id)->pluck('name')->take(4)->all();
            $seUser = \App\Models\User::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'se'))
                ->where('circle_id', $c->id)
                ->first();

            $tested = WaterSample::whereIn('phed_division_id', $phedIds)->count();
            $fit    = WaterSample::whereIn('phed_division_id', $phedIds)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
            $unfit  = WaterSample::whereIn('phed_division_id', $phedIds)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();
            $pctFit = $tested > 0 ? round(($fit / $tested) * 100, 1) : 0;

            $noAction = WaterSample::query()
                ->whereIn('phed_division_id', $phedIds)
                ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
                ->whereNotIn('id', function ($q) { $q->select('water_sample_id')->from('water_sample_actions'); })
                ->count();

            $seEsc = $this->countByDaysSinceUnfitNotification($phedIds, 10, 19);
            $ceEsc = $this->countByDaysSinceUnfitNotification($phedIds, 20);

            $rag = $pctFit < 80 ? 'High' : ($pctFit < 90 ? 'Med' : 'Low');

            return [
                'circle_id'    => $c->id,
                'circle'       => 'SE ' . $c->name . ' Circle',
                'se_name'      => $seUser?->name ?? 'Engr. — (vacant)',
                'districts'    => $districts,
                'tested'       => $tested,
                'fit'          => $fit,
                'unfit'        => $unfit,
                'pct_fit'      => $pctFit,
                'no_action'    => $noAction,
                'se_escalated' => $seEsc,
                'ce_escalated' => $ceEsc,
                'rag'          => $rag,
            ];
        });

        $totals = [
            'tested'       => $rows->sum('tested'),
            'fit'          => $rows->sum('fit'),
            'unfit'        => $rows->sum('unfit'),
            'pct_fit'      => $rows->sum('tested') > 0 ? round(($rows->sum('fit') / max(1, $rows->sum('tested'))) * 100, 1) : 0,
            'no_action'    => $rows->sum('no_action'),
            'se_escalated' => $rows->sum('se_escalated'),
            'ce_escalated' => $rows->sum('ce_escalated'),
        ];

        return [
            'rows'   => $rows->values()->all(),
            'totals' => $totals,
        ];
    }

    private function buildCeEscalatedList(array $phedIds, int $limit = 10): array
    {
        $rows = DB::table('notifications as n')
            ->join('water_samples as ws', 'n.water_sample_id', '=', 'ws.id')
            ->leftJoin('water_schemes as scheme', 'ws.water_scheme_id', '=', 'scheme.id')
            ->leftJoin('districts as d', 'ws.district_id', '=', 'd.id')
            ->leftJoin('phed_divisions as pd', 'ws.phed_division_id', '=', 'pd.id')
            ->leftJoin('circles as c', 'pd.circle_id', '=', 'c.id')
            ->where('n.type_key', 'SAMPLE_UNFIT')
            ->whereNull('n.action_taken_at')
            ->whereNull('ws.deleted_at')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->whereRaw('DATEDIFF(NOW(), n.created_at) >= 20')
            ->select(
                'ws.id',
                'ws.slug',
                'scheme.name as wss_name',
                'd.name as district',
                'c.id as circle_id',
                'c.name as circle_name',
                DB::raw('DATEDIFF(NOW(), n.created_at) as days_elapsed'),
                'n.created_at as xen_notified',
                DB::raw('DATE_ADD(n.created_at, INTERVAL 10 DAY) as se_notified'),
                DB::raw('DATE_ADD(n.created_at, INTERVAL 20 DAY) as ce_notified')
            )
            ->orderByDesc('days_elapsed')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            return [
                'id'           => $r->id,
                'slug'         => $r->slug,
                'wss_name'     => $r->wss_name ?? '—',
                'district'     => $r->district ?? '—',
                'circle_id'    => $r->circle_id,
                'circle'       => $r->circle_name ? 'SE ' . $r->circle_name . ' Circle' : '—',
                'days_elapsed' => (int) $r->days_elapsed,
                'xen_notified' => $r->xen_notified,
                'se_notified'  => $r->se_notified,
                'ce_notified'  => $r->ce_notified,
                'cause'        => 'Biological',
                'parameter'    => 'E. coli',
                'value'        => '—',
            ];
        })->all();
    }

    private function buildApproachingCeList(array $phedIds, int $limit = 100): array
    {
        $rows = DB::table('notifications as n')
            ->join('water_samples as ws', 'n.water_sample_id', '=', 'ws.id')
            ->leftJoin('water_schemes as scheme', 'ws.water_scheme_id', '=', 'scheme.id')
            ->leftJoin('districts as d', 'ws.district_id', '=', 'd.id')
            ->leftJoin('phed_divisions as pd', 'ws.phed_division_id', '=', 'pd.id')
            ->leftJoin('circles as c', 'pd.circle_id', '=', 'c.id')
            ->where('n.type_key', 'SAMPLE_UNFIT')
            ->whereNull('n.action_taken_at')
            ->whereNull('ws.deleted_at')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->whereRaw('DATEDIFF(NOW(), n.created_at) BETWEEN 10 AND 19')
            ->select(
                'ws.id', 'ws.slug',
                'scheme.name as wss_name',
                'd.name as district',
                'c.id as circle_id',
                'c.name as circle_name',
                DB::raw('DATEDIFF(NOW(), n.created_at) as days_elapsed'),
                'n.created_at as xen_notified',
                DB::raw('DATE_ADD(n.created_at, INTERVAL 10 DAY) as se_notified'),
                DB::raw('GREATEST(0, 20 - DATEDIFF(NOW(), n.created_at)) as days_to_ce')
            )
            ->orderBy('days_to_ce')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            return [
                'id'           => $r->id,
                'slug'         => $r->slug,
                'wss_name'     => $r->wss_name ?? '—',
                'district'     => $r->district ?? '—',
                'circle_id'    => $r->circle_id,
                'circle'       => $r->circle_name ? 'SE ' . $r->circle_name . ' Circle' : '—',
                'days_elapsed' => (int) $r->days_elapsed,
                'days_to_ce'   => (int) $r->days_to_ce,
                'xen_notified' => $r->xen_notified,
                'se_notified'  => $r->se_notified,
                'contaminant'  => 'Biological',
            ];
        })->all();
    }

    private function buildPersistentUnfit(array $phedIds, int $limit = 100): array
    {
        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name,circle_id', 'tests'])
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        return $samples->map(function ($s) {
            $tests = $s->tests->sortBy('round')->values();
            $original = $tests->firstWhere('round', 0);
            $r1 = $tests->firstWhere('round', 1);
            $r2 = $tests->firstWhere('round', 2);
            $circle = $s->phedDivision?->circle;

            return [
                'id'           => $s->id,
                'slug'         => $s->slug,
                'wss_name'     => $s->waterScheme?->name ?? '—',
                'district'     => $s->district?->name ?? '—',
                'circle_id'    => $s->phedDivision?->circle_id,
                'circle'       => $s->phedDivision?->circle?->name ? 'SE ' . $s->phedDivision->circle->name . ' Circle' : '—',
                'contaminant'  => 'Chemical',
                'original'     => $original?->remarks ?? '—',
                'r1'           => $r1?->remarks ?? '—',
                'r2'           => $r2?->remarks ?? '—',
                'who_limit'    => '50 µg/L',
                'stage'        => 'R2 Fail',
                'fate_decision'=> 'Secretary',
            ];
        })->all();
    }

    private function buildNotifications(array $phedIds, int $days = 7, int $limit = 8): array
    {
        return DB::table('notifications as n')
            ->leftJoin('water_samples as ws', 'n.water_sample_id', '=', 'ws.id')
            ->leftJoin('water_schemes as scheme', 'ws.water_scheme_id', '=', 'scheme.id')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->whereDate('n.created_at', '>=', now()->subDays($days))
            ->orderByDesc('n.created_at')
            ->limit($limit)
            ->get(['n.id', 'n.type_key', 'n.created_at', 'ws.slug', 'scheme.name as wss_name'])
            ->map(function ($n) {
                $label = match ($n->type_key) {
                    'SAMPLE_UNFIT'      => 'CE Escalated',
                    'RETEST_REQUESTED'  => 'Retest Requested',
                    default             => 'Notification',
                };
                return [
                    'id'         => $n->id,
                    'slug'       => $n->slug ?? '—',
                    'wss_name'   => $n->wss_name ?? '—',
                    'label'      => $label,
                    'created_at' => $n->created_at,
                ];
            })
            ->all();
    }

    private function buildUnfitSamplesGroupedByDistrict(array $phedIds): array
    {
        if (empty($phedIds)) return [];

        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name'])
            ->whereIn('phed_division_id', $phedIds)
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->orderBy('district_id')
            ->orderByDesc('sampled_at')
            ->get();

        $grouped = $samples->groupBy(fn ($s) => $s->district?->name ?? 'Unknown');

        $out = [];
        foreach ($grouped as $district => $rows) {
            $items = [];
            foreach ($rows as $s) {
                $stage = 'No Action';
                $round = (int) $s->current_round;
                $unfitNotif = DB::table('notifications')
                    ->where('water_sample_id', $s->id)
                    ->where('type_key', 'SAMPLE_UNFIT')
                    ->orderBy('created_at')
                    ->first();

                if ($unfitNotif) {
                    $days = (int) Carbon::parse($unfitNotif->created_at)->diffInDays(now());
                    if ($days >= 20) $stage = 'CE Escalated';
                    else if ($days >= 10) $stage = 'SE Escalated';
                    else if ($round > 0) $stage = 'Action Taken';
                    else $stage = 'No Action';
                }

                $items[] = [
                    'id'        => $s->id,
                    'slug'      => $s->slug,
                    'wss_name'  => $s->waterScheme?->name ?? '—',
                    'district'  => $s->district?->name ?? '—',
                    'sampled_at'=> $s->getRawOriginal('sampled_at'),
                    'cause'     => 'Lab Test',
                    'parameter' => '—',
                    'stage'     => $stage,
                    'round'     => $round,
                ];
            }
            $out[] = [
                'district' => $district,
                'rows'     => $items,
            ];
        }
        return $out;
    }

    /** Resolve a sample row's raw `result` / `current_status` to 'Fit' / 'Unfit' / 'Untested'. */
    private function resolveResult($sample): string
    {
        if (!$sample) return 'Untested';
        $r = $sample->result ?? null;
        if ($r !== null && $r !== '') {
            $s = strtolower((string) $r);
            if ($s === '1' || $s === 'fit')   return 'Fit';
            if ($s === '2' || $s === 'unfit') return 'Unfit';
        }
        $cs = (int) ($sample->current_status ?? 0);
        if ($cs === WaterSampleCurrentStatusEnum::FIT->value) return 'Fit';
        if ($cs === WaterSampleCurrentStatusEnum::UNFIT->value) return 'Unfit';
        if ($cs === WaterSampleCurrentStatusEnum::CLOSED->value) return 'Fit';
        return 'Untested';
    }
}
