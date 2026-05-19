<?php

namespace App\Http\Controllers\Secretary;

use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\District;
use App\Models\PhedDivision;
use App\Models\Region;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleTest;
use App\Models\WaterScheme;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as Http;

/**
 * Secretary portal — province-wide oversight (top of the hierarchy).
 *
 * Hierarchy: Secretary → CEs (Regions) → SE Circles → PHE Divisions → XENs.
 * Endpoints return data across ALL regions (no scoping); the Secretary is
 * the approving authority for WSS Fate Decisions escalated by CEs.
 *
 * RBAC: every endpoint gated by a dedicated `view_secretary_*` permission
 * (see permissions ids 211-217). The secretary role gets all 7 by default;
 * admins can revoke individual screens via the Module Access grid.
 */
class SecretaryPortalController extends Controller
{
    /**
     * 403 helper — unscoped admins bypass; everyone else must hold $perm.
     * Mirrors the gating pattern in FinanceExportController.
     */
    private function gate(string $perm): ?JsonResponse
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], Http::HTTP_UNAUTHORIZED);
        }
        if ($user->isUnscoped() || $user->can($perm)) {
            return null;
        }
        return response()->json([
            'message' => 'Not authorized to access this Secretary portal screen',
        ], Http::HTTP_FORBIDDEN);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/me  — identity for the layout
     |──────────────────────────────────────────────────────────────────*/
    public function me(): JsonResponse
    {
        if ($r = $this->gate('view_secretary_portal')) return $r;
        $user = auth()->user()->load(['designation']);

        $ces = Region::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($r) => [
                'id'    => $r->id,
                'name'  => $r->name,
                'label' => $r->name,  // already "CE — Centre" etc.
                'short' => preg_replace('/^CE\s*[\x{2014}\x{2013}-]?\s*/u', '', $r->name),
            ]);

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'designation' => $user->designation?->name ?? 'Secretary',
            'scope_label' => 'Secretary — PHED KP',
            'scope_sub'   => 'Province-wide · All CEs',
            'ces'         => $ces,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/dashboard
     |──────────────────────────────────────────────────────────────────*/
    public function dashboard(): JsonResponse
    {
        if ($r = $this->gate('view_secretary_dashboard')) return $r;
        $row1 = $this->row1();
        $row2 = $this->row2();
        $ceSummary = $this->ceSummary();
        $fateDecisions = $this->buildFateDecisionsPending(limit: 6);
        $cePerformance = $this->cePerformanceList();
        $notifs = $this->secretaryNotifications(limit: 6);

        return response()->json([
            'scope' => [
                'name'    => 'Secretary PHED KP',
                'sub'     => 'Province-wide view across all Chief Engineers',
                'ces'     => Region::orderBy('name')->pluck('name')->all(),
            ],
            'row1'             => $row1,
            'row2'             => $row2,
            'ce_summary'       => $ceSummary,
            'fate_decisions'   => $fateDecisions,
            'ce_performance'   => $cePerformance,
            'notifications'    => $notifs,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/ce/{regionId}  — CE-level unfit trail (read-only)
     |──────────────────────────────────────────────────────────────────*/
    public function ceUnfit(int $regionId): JsonResponse
    {
        if ($r = $this->gate('view_secretary_ce_unfit')) return $r;
        $region = Region::findOrFail($regionId);
        $phedIds = PhedDivision::query()
            ->whereHas('circle', fn ($q) => $q->where('region_id', $regionId))
            ->pluck('id')
            ->all();

        $ceUser = \App\Models\User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'ce'))
            ->where('region_id', $regionId)
            ->first();

        $circles = Circle::where('region_id', $regionId)->orderBy('name')->get(['id', 'name']);

        $totalUnfit = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->count();

        $totalTested = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereNotNull('current_status')
            ->count();

        $noAction = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->whereNotIn('id', function ($q) {
                $q->select('water_sample_id')->from('water_sample_actions');
            })
            ->count();

        $ceEscalated = $this->daysSinceUnfit($phedIds, minDays: 20);
        $persistent = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->count();

        $resolved = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereYear('updated_at', now()->year)
            ->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)
            ->where('current_round', '>', 0)
            ->count();

        $samples = $this->unfitGroupedByCircle($phedIds);

        return response()->json([
            'region' => [
                'id'         => $region->id,
                'name'       => $region->name,
                'short'      => preg_replace('/^CE\s*[\x{2014}\x{2013}-]?\s*/u', '', $region->name),
                'ce_name'    => $ceUser?->name ?? 'Engr. — (vacant)',
                'circles'    => $circles->map(fn ($c) => 'SE ' . $c->name)->all(),
                'circles_descriptive' => $this->describeCircles($circles, $regionId),
            ],
            'stats' => [
                'total_unfit'         => $totalUnfit,
                'pct_of_tested'       => $totalTested > 0 ? round(($totalUnfit / $totalTested) * 100, 1) : 0,
                'no_action'           => $noAction,
                'ce_escalated'        => $ceEscalated,
                'persistent'          => $persistent,
                'resolved_this_year'  => $resolved,
            ],
            'samples_grouped_by_circle' => $samples,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/fate-decisions
     |──────────────────────────────────────────────────────────────────*/
    public function fateDecisions(): JsonResponse
    {
        if ($r = $this->gate('view_secretary_fate_decisions')) return $r;
        $pending = $this->buildFateDecisionsPending(limit: 100);
        $past    = $this->buildFateDecisionsPast(limit: 100);

        $stats = [
            'pending'         => count($pending),
            'issued_ytd'      => count($past),
            'decommissioned'  => collect($past)->where('decision', 'Decommissioned')->count(),
            'public_advisory' => collect($past)->where('decision', 'Public Advisory')->count(),
        ];

        return response()->json([
            'stats'   => $stats,
            'pending' => $pending,
            'past'    => $past,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/persistent-unfit
     |──────────────────────────────────────────────────────────────────*/
    public function persistentUnfit(): JsonResponse
    {
        if ($r = $this->gate('view_secretary_persistent_unfit')) return $r;
        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name,circle_id', 'tests'])
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->orderByDesc('updated_at')
            ->get();

        $groups = [];
        $byCe = $samples->groupBy(function ($s) {
            $c = $s->phedDivision?->circle;
            return $c?->region_id ?? 0;
        });

        $regions = Region::pluck('name', 'id');

        foreach ($byCe as $regionId => $rows) {
            $items = [];
            foreach ($rows as $s) {
                $tests = $s->tests->sortBy('round')->values();
                $original = $tests->firstWhere('round', 0);
                $r1 = $tests->firstWhere('round', 1);
                $r2 = $tests->firstWhere('round', 2);
                $circle = $s->phedDivision?->circle;

                $stage = $s->current_round >= 3 ? 'R3 Fail' : 'R2 Fail';
                $status = 'Pending';
                if (!empty($s->fate_decision ?? null)) {
                    $status = $s->fate_decision === 'advisory' ? 'Public Advisory' : ($s->fate_decision === 'monitor' ? 'Monitoring' : 'Implemented');
                }

                // contaminant + who_limit dropped — no schema backing.
                $items[] = [
                    'id'           => $s->id,
                    'slug'         => $s->slug,
                    'wss_name'     => $s->waterScheme?->name ?? '—',
                    'district'     => $s->district?->name ?? '—',
                    'ce'           => $regions[$regionId] ?? '—',
                    'se_circle'    => $circle?->name ? 'SE ' . $circle->name : '—',
                    'original'     => $original?->remarks ?? '—',
                    'r1'           => $r1?->remarks ?? '—',
                    'r2'           => $r2?->remarks ?? '—',
                    'stage'        => $stage,
                    'status'       => $status,
                ];
            }
            $groups[] = [
                'ce'    => $regions[$regionId] ?? 'Unknown',
                'rows'  => $items,
            ];
        }

        $stats = [
            'total'           => $samples->count(),
            'fate_pending'    => $samples->filter(fn ($s) => empty($s->fate_decision ?? null))->count(),
            'under_monitoring'=> $samples->filter(fn ($s) => ($s->fate_decision ?? null) === 'monitor')->count(),
            'decommissioned'  => $samples->filter(fn ($s) => ($s->fate_decision ?? null) === 'decommission')->count(),
        ];

        return response()->json([
            'stats'  => $stats,
            'groups' => $groups,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/gar  — Province-wide GAR
     |──────────────────────────────────────────────────────────────────*/
    public function gar(Request $request): JsonResponse
    {
        if ($r = $this->gate('view_secretary_gar')) return $r;
        $fromDate = $request->query('from_date');
        $toDate   = $request->query('to_date');
        $regionId = $request->query('region_id');

        $sampleQuery = WaterSample::query()
            ->when($regionId, fn ($q) => $q->whereIn('phed_division_id', PhedDivision::whereHas('circle', fn ($qq) => $qq->where('region_id', $regionId))->pluck('id')))
            ->when($fromDate, fn ($q) => $q->whereDate('sampled_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('sampled_at', '<=', $toDate));

        $tested = (clone $sampleQuery)->count();
        $fit    = (clone $sampleQuery)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
        $unfit  = (clone $sampleQuery)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();

        $wssTotal = WaterScheme::query()
            ->when($regionId, fn ($q) => $q->whereIn('phed_division_id', PhedDivision::whereHas('circle', fn ($qq) => $qq->where('region_id', $regionId))->pluck('id')))
            ->count();

        $labs = DB::table('water_samples as ws')
            ->leftJoin('laboratories as l', 'ws.laboratory_id', '=', 'l.id')
            ->whereNotNull('ws.laboratory_id')
            ->distinct()
            ->pluck('l.name')
            ->filter()
            ->values();

        // CE-wise abstract — one row per Region
        $ceRows = Region::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($r) use ($fromDate, $toDate) {
                $phedIds = PhedDivision::whereHas('circle', fn ($q) => $q->where('region_id', $r->id))->pluck('id')->all();
                $q = WaterSample::query()
                    ->when(!empty($phedIds), fn ($qq) => $qq->whereIn('phed_division_id', $phedIds))
                    ->when($fromDate, fn ($qq) => $qq->whereDate('sampled_at', '>=', $fromDate))
                    ->when($toDate, fn ($qq) => $qq->whereDate('sampled_at', '<=', $toDate));

                $t = (clone $q)->count();
                $f = (clone $q)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
                $u = (clone $q)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();
                $pctUnfit = $t > 0 ? round(($u / $t) * 100, 1) : 0;
                $pctFit   = $t > 0 ? round(($f / $t) * 100, 1) : 0;
                $rag = $pctUnfit >= 20 ? 'high' : ($pctUnfit >= 10 ? 'medium' : 'low');

                $ceUser = \App\Models\User::query()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'ce'))
                    ->where('region_id', $r->id)
                    ->first();

                return [
                    'region_id' => $r->id,
                    'ce'        => $r->name,
                    'short'     => preg_replace('/^CE\s*[\x{2014}\x{2013}-]?\s*/u', '', $r->name),
                    'ce_name'   => $ceUser?->name ?? '—',
                    'tested'    => $t,
                    'fit'       => $f,
                    'unfit'     => $u,
                    'pct_unfit' => $pctUnfit,
                    'pct_fit'   => $pctFit,
                    'rag'       => $rag,
                ];
            });

        return response()->json([
            'scope' => [
                'name'    => 'Secretary PHED KP',
                'sub'     => 'Province-wide · All Chief Engineers · All Circles',
            ],
            'kpi' => [
                'total_tested' => $tested,
                'fit'          => $fit,
                'unfit'        => $unfit,
                'pct_unfit'    => $tested > 0 ? round(($unfit / $tested) * 100, 1) : 0,
                'wss_covered'  => (clone $sampleQuery)->whereNotNull('water_scheme_id')->distinct('water_scheme_id')->count('water_scheme_id'),
                'wss_total'    => $wssTotal,
                'lab_count'    => $labs->count(),
                'lab_names'    => $labs->all(),
            ],
            'ce_abstract' => $ceRows,
            'regions'     => Region::orderBy('name')->get(['id', 'name'])->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /secretary/wss-register — province-wide
     |──────────────────────────────────────────────────────────────────*/
    public function wssRegister(Request $request): JsonResponse
    {
        if ($r = $this->gate('view_secretary_wss_register')) return $r;
        $q          = $request->query('q');
        $regionId   = $request->query('region_id');
        $districtId = $request->query('district_id');
        $result     = $request->query('result');

        $schemes = WaterScheme::query()
            ->with(['district:id,name,circle_id', 'phedDivision:id,name,circle_id'])
            ->when($regionId, fn ($qx) => $qx->whereHas('phedDivision', fn ($qq) => $qq->whereHas('circle', fn ($cq) => $cq->where('region_id', $regionId))))
            ->when($q, fn ($qx) => $qx->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('address', 'like', "%{$q}%");
            }))
            ->when($districtId, fn ($qx) => $qx->where('district_id', $districtId))
            ->orderBy('name')
            ->get();

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
            ->get(['id', 'water_scheme_id', 'current_status', 'sampled_at', 'result', 'current_round'])
            ->groupBy('water_scheme_id')
            ->map(fn ($rows) => $rows->first());

        // Map circle → region
        $circles = Circle::query()->get(['id', 'name', 'region_id'])->keyBy('id');
        $regions = Region::pluck('name', 'id');

        $rows = $schemes->map(function ($w) use ($latestSamples, $resultPerScheme, $circles, $regions) {
            $info = $latestSamples[$w->id] ?? null;
            $last = $resultPerScheme[$w->id] ?? null;
            $result = $this->resolveResult($last);
            $resultLabel = $result;
            if ($result === 'Unfit' && $last && ($last->current_round ?? 0) >= 2) {
                $resultLabel = 'Unfit R' . $last->current_round;
                $opStatus = 'Persistent Unfit';
            } else {
                $opStatus = 'Operational';
            }

            $nextScheduled = null;
            $overdue = false;
            if ($info && $info->last_sampled_at) {
                $nextDt = Carbon::parse($info->last_sampled_at)->addMonths(3);
                $nextScheduled = $nextDt->toDateString();
                $overdue = $nextDt->isPast();
            }

            $circle = $w->phedDivision ? $circles->get($w->phedDivision->circle_id) : null;
            $region = $circle ? ($regions[$circle->region_id] ?? null) : null;

            return [
                'id'             => $w->id,
                'wss_code'       => 'WSS-' . str_pad($w->id, 4, '0', STR_PAD_LEFT),
                'wss_name'       => $w->name,
                'district'       => $w->district?->name ?? '—',
                'ce'             => $region ?? '—',
                'region_id'      => $circle?->region_id,
                'se_circle'      => $circle?->name ? 'SE ' . $circle->name : '—',
                'source_type'    => $w->source_type ?? 'Tube Well',
                'power_input'    => $w->power_input,
                'operational_status' => $opStatus,
                'times_tested'   => (int) ($info->times_tested ?? 0),
                'last_result'    => $resultLabel,
                'last_result_raw'=> $result,
                'last_sampled_at'=> $info?->last_sampled_at,
                'next_scheduled' => $nextScheduled,
                'overdue'        => $overdue,
            ];
        });

        if ($result) $rows = $rows->where('last_result_raw', $result)->values();

        $stats = [
            'total'      => $rows->count(),
            'last_fit'   => $rows->where('last_result_raw', 'Fit')->count(),
            'last_unfit' => $rows->where('last_result_raw', 'Unfit')->count(),
            'untested'   => $rows->where('last_result_raw', 'Untested')->count(),
            'overdue'    => $rows->where('overdue', true)->count(),
        ];

        return response()->json([
            'stats'   => $stats,
            'rows'    => $rows->values(),
            'regions' => Region::orderBy('name')->get(['id', 'name']),
            'districts' => District::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  Builders
     |──────────────────────────────────────────────────────────────────*/

    private function row1(): array
    {
        // FUNCTIONAL WSS must filter by water_schemes.is_active = 1 — the
        // label promises "functional" not "all schemes ever created".
        $schemes = WaterScheme::query()
            ->where('is_active', 1)
            ->select(DB::raw('COUNT(*) as total, SUM(CASE WHEN LOWER(power_input) LIKE "%solar%" THEN 1 ELSE 0 END) as solar'))
            ->first();
        $solar = (int) ($schemes->solar ?? 0);

        $testedWss = WaterSample::query()
            ->whereNotNull('water_scheme_id')
            ->distinct('water_scheme_id')
            ->count('water_scheme_id');

        $counts = WaterSample::query()
            ->whereNotNull('current_status')
            ->select(DB::raw('
                COUNT(*) as total,
                SUM(CASE WHEN current_status = ' . WaterSampleCurrentStatusEnum::FIT->value . ' THEN 1 ELSE 0 END) as fit,
                SUM(CASE WHEN current_status = ' . WaterSampleCurrentStatusEnum::UNFIT->value . ' THEN 1 ELSE 0 END) as unfit
            '))
            ->first();

        $tested = (int) ($counts->total ?? 0);
        $fit    = (int) ($counts->fit ?? 0);
        $unfit  = (int) ($counts->unfit ?? 0);

        // Unfit follow-up rate (samples that have any retest)
        $followup = $this->buildFollowup();

        return [
            'functional_wss'  => [
                'total'     => (int) ($schemes->total ?? 0),
                'solar'     => $solar,
                'non_solar' => max(0, ((int) ($schemes->total ?? 0)) - $solar),
            ],
            'tested_wss'      => $testedWss,
            'tested_samples'  => $tested,
            'fit_samples'     => $fit,
            'unfit_samples'   => $unfit,
            'unfit_followup'  => $followup,
        ];
    }

    private function row2(): array
    {
        $fatePending = $this->countFatePending();
        // "Persistent Unfit WSS" must count distinct schemes, not samples.
        // A single scheme can have multiple unfit samples at R2; counting
        // samples would inflate this number once real data lands.
        $persistent  = WaterSample::query()
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->whereNotNull('water_scheme_id')
            ->distinct('water_scheme_id')
            ->count('water_scheme_id');

        $ceEscalated = $this->daysSinceUnfit([], minDays: 20);

        $coverage = $this->monthlyCoverage();
        $resolved = WaterSample::query()
            ->whereYear('updated_at', now()->year)
            ->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)
            ->where('current_round', '>', 0)
            ->count();

        return [
            'fate_decisions_pending' => $fatePending,
            'persistent_unfit_wss'   => $persistent,
            'ce_escalated_active'    => $ceEscalated,
            'monthly_coverage'       => $coverage,
            'resolved_this_year'     => $resolved,
        ];
    }

    private function ceSummary(): array
    {
        $rows = Region::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($r) {
                $circles = Circle::where('region_id', $r->id)->pluck('name')->all();
                $districtCount = District::where('circle_id', '!=', null)
                    ->whereIn('circle_id', Circle::where('region_id', $r->id)->pluck('id'))
                    ->count();
                $phedIds = PhedDivision::whereHas('circle', fn ($q) => $q->where('region_id', $r->id))->pluck('id')->all();
                $ceUser = \App\Models\User::query()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'ce'))
                    ->where('region_id', $r->id)
                    ->first();

                $tested = WaterSample::whereIn('phed_division_id', $phedIds)->count();
                $fit    = WaterSample::whereIn('phed_division_id', $phedIds)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
                $unfit  = WaterSample::whereIn('phed_division_id', $phedIds)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();
                $pctFit = $tested > 0 ? round(($fit / $tested) * 100, 1) : 0;

                $ceEsc = $this->daysSinceUnfit($phedIds, minDays: 20);
                $fateDec = $this->countFatePending($phedIds);

                $rag = $pctFit < 80 ? 'High' : ($pctFit < 90 ? 'Med' : 'Low');

                return [
                    'region_id'      => $r->id,
                    'ce'             => $r->name,
                    'short'          => preg_replace('/^CE\s*[\x{2014}\x{2013}-]?\s*/u', '', $r->name),
                    'ce_name'        => $ceUser?->name ?? 'Engr. — (vacant)',
                    'circles'        => array_map(fn ($c) => 'SE ' . $c, $circles),
                    'districts_count'=> $districtCount,
                    'tested'         => $tested,
                    'fit'            => $fit,
                    'unfit'          => $unfit,
                    'pct_fit'        => $pctFit,
                    'ce_escalated'   => $ceEsc,
                    'fate_decisions' => $fateDec,
                    'rag'            => $rag,
                ];
            });

        $totals = [
            'tested'         => $rows->sum('tested'),
            'fit'            => $rows->sum('fit'),
            'unfit'          => $rows->sum('unfit'),
            'pct_fit'        => $rows->sum('tested') > 0 ? round(($rows->sum('fit') / max(1, $rows->sum('tested'))) * 100, 1) : 0,
            'ce_escalated'   => $rows->sum('ce_escalated'),
            'fate_decisions' => $rows->sum('fate_decisions'),
        ];

        return ['rows' => $rows->values()->all(), 'totals' => $totals];
    }

    private function cePerformanceList(): array
    {
        return collect($this->ceSummary()['rows'])->map(function ($r) {
            return [
                'region_id' => $r['region_id'],
                'ce'        => $r['ce'],
                'sub'       => "{$r['pct_fit']}% fit · {$r['ce_escalated']} CE esc.",
                'rag'       => $r['rag'],
            ];
        })->all();
    }

    private function buildFateDecisionsPending(int $limit = 100): array
    {
        $hasTransferCols = \Illuminate\Support\Facades\Schema::hasColumn('water_samples', 'transferred_to_secretary_at');

        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name,circle_id', 'tests'])
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            // Surface XEN-transferred samples first so the Secretary sees
            // explicit hand-offs at the top, then anything else that is
            // auto-flagged as Persistent Unfit.
            ->when($hasTransferCols, fn ($q) => $q->orderByRaw('transferred_to_secretary_at IS NULL ASC'))
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $regions = Region::pluck('name', 'id');

        // Fetch the transferring XEN's name in bulk
        $xenIds = $samples->pluck('transferred_to_secretary_by')->filter()->unique();
        $xenNames = $xenIds->isEmpty()
            ? collect()
            : \App\Models\User::whereIn('id', $xenIds)->pluck('name', 'id');

        return $samples->map(function ($s) use ($regions, $xenNames) {
            $tests = $s->tests->sortBy('round')->values();
            $r0 = $tests->firstWhere('round', 0);
            $r1 = $tests->firstWhere('round', 1);
            $r2 = $tests->firstWhere('round', 2);
            $circle = $s->phedDivision?->circle;
            $regionName = $circle ? ($regions[$circle->region_id] ?? '—') : '—';

            return [
                'id'             => $s->id,
                'slug'           => $s->slug,
                'wss_name'       => $s->waterScheme?->name ?? '—',
                'district'       => $s->district?->name ?? '—',
                'ce'             => $regionName,
                'ce_short'       => preg_replace('/^CE\s*[\x{2014}\x{2013}-]?\s*/u', '', $regionName),
                'original'       => $r0?->remarks ?? '—',
                'r1'             => $r1?->remarks ?? '—',
                'r2'             => $r2?->remarks ?? '—',
                'stage'          => $s->current_round >= 3 ? 'R3 Fail' : 'R2 Fail',
                'transferred_at'      => $s->transferred_to_secretary_at,
                'transferred_by_name' => $s->transferred_to_secretary_by ? ($xenNames[$s->transferred_to_secretary_by] ?? null) : null,
                'transferred_remarks' => $s->transferred_to_secretary_remarks,
            ];
        })->all();
    }

    private function buildFateDecisionsPast(int $limit = 100): array
    {
        // WaterSampleTestController::recordFate stamps the decision into the
        // sample's `remarks` field (prefixed `FATE DECISION:`) and flips
        // `is_closed = 1` + `current_status = CLOSED`. We key off those two
        // signals instead of a dedicated column.
        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name,circle_id'])
            ->where('is_closed', 1)
            ->where('remarks', 'like', 'FATE DECISION:%')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $regions = Region::pluck('name', 'id');

        return $samples->map(function ($s) use ($regions) {
            $circle = $s->phedDivision?->circle;
            $regionName = $circle ? ($regions[$circle->region_id] ?? '—') : '—';

            // Decision is the first segment of remarks, e.g.
            //   "FATE DECISION: CONTINUE MONITORING | Auth: ... | Remarks: ..."
            // recordFate stores the *label* (not the raw enum), so we match on
            // the label suffix.
            $remarks = (string) ($s->remarks ?? '');
            $rawDecision = '';
            if (preg_match('/FATE DECISION:\s*([^|]+)/i', $remarks, $m)) {
                $rawDecision = trim($m[1]);
            }
            $upper = strtoupper($rawDecision);
            $decisionLabel = match (true) {
                str_contains($upper, 'DECOMMISSION') => 'Decommissioned',
                str_contains($upper, 'ADVISORY')     => 'Public Advisory',
                str_contains($upper, 'MONITORING')   => 'Continue Monitoring',
                default                              => $rawDecision ?: 'Recorded',
            };
            $statusLabel = match ($decisionLabel) {
                'Decommissioned'     => 'Implemented',
                'Public Advisory'    => 'Advisory Issued',
                'Continue Monitoring'=> 'Monitoring Active',
                default              => 'Recorded',
            };
            $rawUpdated = $s->getRawOriginal('updated_at');
            return [
                'id'        => $s->id,
                'wss_name'  => $s->waterScheme?->name ?? '—',
                'district'  => $s->district?->name ?? '—',
                'ce'        => $regionName,
                'decision'  => $decisionLabel,
                'date'      => $rawUpdated ? Carbon::parse($rawUpdated)->toDateString() : null,
                'status'    => $statusLabel,
            ];
        })->all();
    }

    private function secretaryNotifications(int $limit = 6): array
    {
        $out = [];
        // Fate decisions pending — one entry per WSS
        $pending = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name,circle_id'])
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();

        $regions = Region::pluck('name', 'id');
        foreach ($pending as $s) {
            $circle = $s->phedDivision?->circle;
            $regionName = $circle ? ($regions[$circle->region_id] ?? '—') : '—';
            $rawUpdated = $s->getRawOriginal('updated_at');
            $out[] = [
                'type'      => 'action',
                'label'     => 'Fate Decision Required',
                'title'     => ($s->waterScheme?->name ?? '—') . ' — Chemical R' . $s->current_round,
                'meta'      => ($rawUpdated ? Carbon::parse($rawUpdated)->format('d-M-y H:i') : '—') . ' · ' . $regionName,
                'badge'     => 'Action',
            ];
        }

        // Pad with CE-escalated alert if there's still room AND the alert is
        // actually meaningful (>0 unresolved cases). The previous version
        // also padded with a synthetic "Monthly Report Ready" info — removed,
        // because it surfaced every time the real list was short, masking
        // the fact that there was nothing to notify about.
        if (count($out) < $limit) {
            $ceEscalated = $this->daysSinceUnfit([], minDays: 20);
            if ($ceEscalated > 0) {
                $out[] = [
                    'type'  => 'alert',
                    'label' => 'CE Escalated (Unresolved)',
                    'title' => $ceEscalated . ' cases — no action >20 days',
                    'meta'  => now()->format('d-M-y H:i'),
                    'badge' => 'Alert',
                ];
            }
        }

        return array_slice($out, 0, $limit);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  Small helpers
     |──────────────────────────────────────────────────────────────────*/

    private function describeCircles($circles, int $regionId): array
    {
        return $circles->map(function ($c) {
            $abbr = District::where('circle_id', $c->id)->pluck('name')
                ->map(fn ($n) => substr($n, 0, 3))
                ->take(3)
                ->all();
            return 'SE ' . $c->name . ' (' . implode(', ', $abbr) . ')';
        })->all();
    }

    private function daysSinceUnfit(array $phedIds, int $minDays): int
    {
        $q = DB::table('notifications as n')
            ->join('water_samples as ws', 'n.water_sample_id', '=', 'ws.id')
            ->where('n.type_key', 'SAMPLE_UNFIT')
            ->whereNull('n.action_taken_at')
            ->whereNull('ws.deleted_at')
            ->when(!empty($phedIds), fn ($qq) => $qq->whereIn('ws.phed_division_id', $phedIds))
            ->whereRaw('DATEDIFF(NOW(), n.created_at) >= ?', [$minDays]);
        return $q->distinct('n.water_sample_id')->count('n.water_sample_id');
    }

    private function countFatePending(array $phedIds = []): int
    {
        return WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->where('current_round', '>=', 2)
            ->count();
    }

    private function monthlyCoverage(): array
    {
        // 15% of Functional WSS must be tested per month (SRS KPI).
        $totalWss = WaterScheme::query()->where('is_active', 1)->count();
        $target = (int) ceil($totalWss * 0.15);
        $monthStart = now()->startOfMonth()->toDateTimeString();
        $tested = WaterSample::query()
            ->where('sampled_at', '>=', $monthStart)
            ->whereNotNull('water_scheme_id')
            ->distinct('water_scheme_id')
            ->count('water_scheme_id');

        // "Labs on target" — previously split the province-wide target equally
        // across all labs, which over-credited small labs and penalised large
        // ones (labs serve regions of very different sizes — Centre vs Hub).
        // Honest metric: count labs that actually tested ANY WSS this month
        // (i.e. labs that submitted samples). Total = all registered labs.
        $labCount = DB::table('laboratories')->whereNull('deleted_at')->count();
        $labsActive = DB::table('water_samples')
            ->whereNotNull('laboratory_id')
            ->whereNotNull('water_scheme_id')
            ->where('sampled_at', '>=', $monthStart)
            ->distinct('laboratory_id')
            ->count('laboratory_id');

        return [
            'target_pct'      => 15,
            'tested_wss'      => $tested,
            'target_wss'      => $target,
            'labs_on_target'  => $labsActive,  // labs that submitted samples this month
            'total_labs'      => $labCount,
        ];
    }

    private function buildFollowup(): array
    {
        $unfitSampleIds = WaterSampleTest::query()
            ->where('result', WaterSampleTestResultEnum::UNFIT->value)
            ->distinct()
            ->pluck('water_sample_id');

        $fit = 0; $stillUnfit = 0; $closed = 0; $total = 0;
        foreach ($unfitSampleIds as $sid) {
            $total++;
            $latest = WaterSampleTest::where('water_sample_id', $sid)->orderByDesc('round')->first();
            if (!$latest) continue;
            $resultVal = $latest->result instanceof \BackedEnum ? $latest->result->value : $latest->result;
            if ($latest->round > 0) {
                if ((int) $resultVal === WaterSampleTestResultEnum::FIT->value) $fit++;
                else if ((int) $resultVal === WaterSampleTestResultEnum::UNFIT->value) $stillUnfit++;
            }
            if (WaterSample::find($sid)?->is_closed) $closed++;
        }
        $rate = $total > 0 ? round((($fit + $stillUnfit + $closed) / $total) * 100, 0) : 0;
        return [
            'total'         => $total,
            'fit'           => $fit,
            'still_unfit'   => $stillUnfit,
            'closed'        => $closed,
            'rate_percent'  => $rate,
        ];
    }

    private function unfitGroupedByCircle(array $phedIds): array
    {
        if (empty($phedIds)) return [];

        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name,circle_id'])
            ->whereIn('phed_division_id', $phedIds)
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->orderByDesc('sampled_at')
            ->get();

        $circles = Circle::pluck('name', 'id');
        $grouped = $samples->groupBy(fn ($s) => $s->phedDivision?->circle_id ?? 0);

        $out = [];
        foreach ($grouped as $circleId => $rows) {
            $cName = $circles[$circleId] ?? 'Unknown';
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
                }
                if ($round >= 2) $stage = 'Persistent Unfit';

                $items[] = [
                    'id'        => $s->id,
                    'slug'      => $s->slug,
                    'wss_name'  => $s->waterScheme?->name ?? '—',
                    'district'  => $s->district?->name ?? '—',
                    'se_circle' => 'SE ' . $cName,
                    'cause'     => 'Lab Test',
                    'parameter' => '—',
                    'status'    => $stage,
                    'stage'     => $round > 0 ? 'R' . $round : '—',
                ];
            }
            $out[] = ['circle' => 'SE ' . $cName . ' Circle', 'rows' => $items];
        }
        return $out;
    }

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
