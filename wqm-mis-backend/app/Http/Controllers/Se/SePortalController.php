<?php

namespace App\Http\Controllers\Se;

use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Enums\WaterSampleTestStatusEnum;
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
 * Superintendent Engineer (SE) portal — circle-scoped oversight.
 *
 * Hierarchy: Secretary → CE (Region) → SE (Circle) → XEN (PHE Division) → samples.
 * Every endpoint here filters by the SE's circle_id so they only see WSS,
 * samples and retests inside their circle. Falls back to "unscoped" (no filter)
 * for users without circle_id so an admin can still hit the URLs during dev.
 */
class SePortalController extends Controller
{
    /* ──────────────────────────────────────────────────────────────────
     |  Scope helpers
     |──────────────────────────────────────────────────────────────────*/

    /** @return int[] phed_division ids under the SE's circle (empty = unrestricted) */
    private function phedDivisionIds(): array
    {
        $user = auth()->user();
        if (!$user || !$user->circle_id) return [];
        return PhedDivision::where('circle_id', $user->circle_id)->pluck('id')->all();
    }

    /** @return int[] district ids under the SE's circle (empty = unrestricted) */
    private function districtIds(): array
    {
        $user = auth()->user();
        if (!$user || !$user->circle_id) return [];
        return District::where('circle_id', $user->circle_id)->pluck('id')->all();
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/me
     |──────────────────────────────────────────────────────────────────*/
    public function me(): JsonResponse
    {
        $user = auth()->user()->load(['circle.region', 'district', 'designation']);
        $districts = District::query()
            ->when($user->circle_id, fn ($q) => $q->where('circle_id', $user->circle_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name]);

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'phone'       => $user->phone,
            'designation' => $user->designation?->name ?? 'Superintendent Engineer',
            'circle'      => $user->circle ? ['id' => $user->circle->id, 'name' => $user->circle->name] : null,
            'region'      => $user->circle?->region ? ['id' => $user->circle->region->id, 'name' => $user->circle->region->name] : null,
            'districts'   => $districts,
            'scope_label' => $user->circle ? 'SE — ' . $user->circle->name . ' Circle' : 'SE — (unassigned)',
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/dashboard
     |──────────────────────────────────────────────────────────────────*/
    public function dashboard(): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();

        // KPI cards
        $unfitNoAction = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->whereNotIn('id', fn ($q) => $q->select('water_sample_id')->from('water_sample_actions'))
            ->count();

        $retestsPending = WaterSampleTest::query()
            ->join('water_samples as ws', 'water_sample_tests.water_sample_id', '=', 'ws.id')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->where('water_sample_tests.round', '>', 0)
            ->where('water_sample_tests.status', WaterSampleTestStatusEnum::PENDING->value)
            ->count();

        $overdueWss = $this->overdueWss($phedIds)['count'];

        $resolvedThisYear = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereYear('updated_at', now()->year)
            ->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)
            ->where('current_round', '>', 0)
            ->count();

        // Unfit Samples — Action Required (with stage status)
        $unfitSamples = $this->buildUnfitSamples($phedIds, limit: 20);

        // Retest Samples table — My Area
        $retestSamples = $this->buildRetestSamples($phedIds, limit: 10);

        // Overdue WSS sidebar panel
        $overduePanel = $this->overdueWss($phedIds, limit: 6)['rows'];

        // Notifications panel (7 days)
        $notifications = $this->notificationsPanel($phedIds, days: 7, limit: 6);

        return response()->json([
            'scope' => [
                'name'      => $user->circle?->name ?? '—',
                'label'     => $user->circle?->name ? ($user->circle->name . ' Circle') : '—',
                'districts' => District::where('circle_id', $user->circle_id)->orderBy('name')->pluck('name')->all(),
            ],
            'stats' => [
                'unfit_no_action'    => $unfitNoAction,
                'retests_pending'    => $retestsPending,
                'overdue_wss'        => $overdueWss,
                'resolved_this_year' => $resolvedThisYear,
            ],
            'unfit_samples'   => $unfitSamples,
            'retest_samples'  => $retestSamples,
            'overdue_panel'   => $overduePanel,
            'notifications'   => $notifications,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/unfit-trail
     |──────────────────────────────────────────────────────────────────*/
    public function unfitTrail(Request $request): JsonResponse
    {
        $phedIds = $this->phedDivisionIds();
        $q       = $request->query('q');
        $status  = $request->query('status');

        $rows = $this->buildUnfitSamples($phedIds, limit: 500, q: $q, status: $status);

        $totalUnfit  = collect($rows)->count();
        $noAction    = collect($rows)->where('status', 'No Action')->count();
        $actionTaken = collect($rows)->where('status', 'Action Taken')->count();
        $renotified  = collect($rows)->where('status', 'Re-notified')->count();
        $resolved    = WaterSample::query()
            ->when(!empty($phedIds), fn ($q2) => $q2->whereIn('phed_division_id', $phedIds))
            ->whereYear('updated_at', now()->year)
            ->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)
            ->where('current_round', '>', 0)
            ->count();

        // Group rows by district
        $grouped = [];
        foreach (collect($rows)->groupBy('district') as $district => $items) {
            $grouped[] = ['district' => $district, 'rows' => $items->values()->all()];
        }

        return response()->json([
            'stats' => [
                'total_unfit'  => $totalUnfit,
                'no_action'    => $noAction,
                'action_taken' => $actionTaken,
                're_notified'  => $renotified,
                'resolved'     => $resolved,
            ],
            'groups' => $grouped,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/retest-samples
     |──────────────────────────────────────────────────────────────────*/
    public function retestSamples(): JsonResponse
    {
        $phedIds = $this->phedDivisionIds();

        $retests = WaterSampleTest::query()
            ->with(['waterSample.waterScheme:id,name', 'waterSample.district:id,name'])
            ->join('water_samples as ws', 'water_sample_tests.water_sample_id', '=', 'ws.id')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->where('water_sample_tests.round', '>', 0)
            ->orderByDesc('water_sample_tests.created_at')
            ->select('water_sample_tests.*')
            ->get();

        $rows = $retests->map(function ($t) {
            $statusVal = $t->status instanceof \BackedEnum ? $t->status->value : $t->status;
            $resultVal = $t->result instanceof \BackedEnum ? $t->result->value : $t->result;
            $statusLabel = match ((int) $statusVal) {
                WaterSampleTestStatusEnum::PENDING->value     => 'Awaiting Analysis',
                WaterSampleTestStatusEnum::IN_PROGRESS->value => 'In Analysis',
                WaterSampleTestStatusEnum::COMPLETED->value   => 'Analysed',
                default                                       => 'Pending',
            };
            $resultLabel = match ((int) ($resultVal ?? 0)) {
                WaterSampleTestResultEnum::FIT->value   => 'Fit',
                WaterSampleTestResultEnum::UNFIT->value => 'Unfit',
                default                                 => null,
            };

            // Original (round=0) sample
            $original = WaterSampleTest::where('water_sample_id', $t->water_sample_id)
                ->where('round', 0)->first();

            return [
                'id'              => $t->id,
                'retest_slug'     => $t->waterSample?->slug ? $t->waterSample->slug . ' R' . $t->round : '—',
                'original_slug'   => $t->waterSample?->slug ?? '—',
                'water_sample_id' => $t->water_sample_id,
                'wss_name'        => $t->waterSample?->waterScheme?->name ?? '—',
                'district'        => $t->waterSample?->district?->name ?? '—',
                'round'           => $t->round,
                'stage'           => 'R' . $t->round,
                'sampled_at'      => $t->getRawOriginal('sampled_at'),
                'collection_date' => $t->getRawOriginal('sampled_at'),
                'analyzed_at'     => $t->getRawOriginal('analyzed_at'),
                'status'          => $statusLabel,
                'result'          => $resultLabel,
                'cause'           => $original?->remarks ?: 'Lab Test',
            ];
        });

        $stats = [
            'awaiting' => $rows->where('status', 'Awaiting Analysis')->count(),
            'in_analysis' => $rows->where('status', 'In Analysis')->count(),
            'fit'      => $rows->where('result', 'Fit')->count(),
            'still_unfit' => $rows->where('result', 'Unfit')->count(),
        ];

        $sections = [
            'active'      => $rows->whereIn('status', ['Awaiting Analysis','In Analysis'])->values()->all(),
            'resolved'    => $rows->where('result', 'Fit')->values()->all(),
            'still_unfit' => $rows->where('result', 'Unfit')->values()->all(),
        ];

        return response()->json([
            'stats'    => $stats,
            'sections' => $sections,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/gar
     |──────────────────────────────────────────────────────────────────*/
    public function gar(Request $request): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();

        $fromDate   = $request->query('from_date');
        $toDate     = $request->query('to_date');
        $districtId = $request->query('district_id');

        if ($districtId) {
            $phedIds = PhedDivision::whereIn('id', $phedIds)->where('district_id', $districtId)->pluck('id')->all();
        }

        $sampleQuery = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->when($fromDate, fn ($q) => $q->whereDate('sampled_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('sampled_at', '<=', $toDate));

        $tested = (clone $sampleQuery)->count();
        $fit    = (clone $sampleQuery)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
        $unfit  = (clone $sampleQuery)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();
        $pctUnfit = $tested > 0 ? round(($unfit / $tested) * 100, 1) : 0;

        $wssTotal = WaterScheme::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->count();
        $wssCovered = (clone $sampleQuery)
            ->whereNotNull('water_scheme_id')
            ->distinct('water_scheme_id')
            ->count('water_scheme_id');

        // District-wise abstract
        $districtIds = District::where('circle_id', $user->circle_id)->pluck('id', 'name');
        $byDistrict = District::query()
            ->when($user->circle_id, fn ($q) => $q->where('circle_id', $user->circle_id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($d) use ($phedIds, $fromDate, $toDate) {
                $dPhedIds = PhedDivision::whereIn('id', $phedIds)->where('district_id', $d->id)->pluck('id')->all();
                $q = WaterSample::query()
                    ->when(!empty($dPhedIds), fn ($qq) => $qq->whereIn('phed_division_id', $dPhedIds))
                    ->when($fromDate, fn ($qq) => $qq->whereDate('sampled_at', '>=', $fromDate))
                    ->when($toDate, fn ($qq) => $qq->whereDate('sampled_at', '<=', $toDate));
                $t = (clone $q)->count();
                $f = (clone $q)->where('current_status', WaterSampleCurrentStatusEnum::FIT->value)->count();
                $u = (clone $q)->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)->count();
                $pct = $t > 0 ? round(($u / $t) * 100, 1) : 0;
                $pctFit = $t > 0 ? round(($f / $t) * 100, 1) : 0;
                $rag = $pct >= 20 ? 'high' : ($pct >= 10 ? 'moderate' : 'good');
                $phedDiv = PhedDivision::where('district_id', $d->id)->first();
                return [
                    'district_id' => $d->id,
                    'district'    => $d->name,
                    'phe_division'=> $phedDiv?->name ?? '—',
                    'tested'      => $t,
                    'fit'         => $f,
                    'unfit'       => $u,
                    'pct_unfit'   => $pct,
                    'pct_fit'     => $pctFit,
                    'rag'         => $rag,
                ];
            })
            ->values()
            ->all();

        // Month-wise abstract (last 9 months Jul..Mar style)
        $monthly = $this->buildMonthlyAbstract($phedIds, $fromDate, $toDate);

        return response()->json([
            'scope' => [
                'circle'    => $user->circle?->name ?? '—',
                'districts' => District::where('circle_id', $user->circle_id)->orderBy('name')->pluck('name')->all(),
            ],
            'kpi' => [
                'total_tested' => $tested,
                'fit'          => $fit,
                'unfit'        => $unfit,
                'pct_unfit'    => $pctUnfit,
                'wss_covered'  => $wssCovered,
                'wss_total'    => $wssTotal,
            ],
            'district_abstract' => $byDistrict,
            'month_abstract'    => $monthly,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/gsr
     |──────────────────────────────────────────────────────────────────*/
    public function gsr(Request $request): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();

        $fromDate   = $request->query('from_date');
        $toDate     = $request->query('to_date');
        $districtId = $request->query('district_id');
        $result     = $request->query('result');

        $effectivePheds = $phedIds;
        if ($districtId) {
            $effectivePheds = PhedDivision::whereIn('id', $phedIds)->where('district_id', $districtId)->pluck('id')->all();
        }

        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name', 'phedDivision:id,name'])
            ->when(!empty($effectivePheds), fn ($q) => $q->whereIn('phed_division_id', $effectivePheds))
            ->when($fromDate, fn ($q) => $q->whereDate('sampled_at', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('sampled_at', '<=', $toDate))
            ->orderBy('district_id')
            ->orderByDesc('sampled_at')
            ->get();

        $rows = $samples->map(function ($s) {
            $cs = (int) ($s->current_status instanceof \BackedEnum ? $s->current_status->value : $s->current_status);
            $resultLbl = match ($cs) {
                WaterSampleCurrentStatusEnum::FIT->value   => 'Fit',
                WaterSampleCurrentStatusEnum::UNFIT->value => 'Unfit',
                default                                    => null,
            };
            return [
                'id'           => $s->id,
                'slug'         => $s->slug,
                'wss_name'     => $s->waterScheme?->name ?? '—',
                'sampling_date'=> $s->getRawOriginal('sampled_at'),
                'point'        => $s->sampling_point?->value ?? $s->sampling_point ?? '—',
                'district'     => $s->district?->name ?? '—',
                'phe_division' => $s->phedDivision?->name ?? '—',
                'type'         => $s->test_type?->value ?? $s->test_type ?? '—',
                'result'       => $resultLbl,
                'cause'        => $resultLbl === 'Unfit' ? ($s->remarks ?: '—') : null,
                'parameter'    => '—',
            ];
        });

        if ($result) $rows = $rows->where('result', $result)->values();

        $stats = [
            'total'       => $rows->count(),
            'fit'         => $rows->where('result', 'Fit')->count(),
            'unfit'       => $rows->where('result', 'Unfit')->count(),
            'pct_unfit'   => $rows->count() > 0 ? round(($rows->where('result', 'Unfit')->count() / $rows->count()) * 100, 1) : 0,
            'wss_covered' => $rows->pluck('wss_name')->unique()->count(),
        ];

        $grouped = [];
        foreach ($rows->groupBy('district') as $d => $items) {
            $grouped[] = ['district' => $d, 'rows' => $items->values()->all()];
        }

        return response()->json([
            'scope' => [
                'circle'    => $user->circle?->name ?? '—',
                'districts' => District::where('circle_id', $user->circle_id)->orderBy('name')->pluck('name')->all(),
            ],
            'stats'  => $stats,
            'groups' => $grouped,
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/isr  &  /se/isr/{id}
     |──────────────────────────────────────────────────────────────────*/
    public function isrList(Request $request): JsonResponse
    {
        $phedIds = $this->phedDivisionIds();

        $q          = $request->query('q');
        $wss        = $request->query('wss');
        $districtId = $request->query('district_id');
        $date       = $request->query('date');
        $result     = $request->query('result');

        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name'])
            ->when(!empty($phedIds), fn ($qx) => $qx->whereIn('phed_division_id', $phedIds))
            ->when($q,   fn ($qx) => $qx->where('slug', 'like', "%{$q}%"))
            ->when($districtId, fn ($qx) => $qx->where('district_id', $districtId))
            ->when($date, fn ($qx) => $qx->whereDate('sampled_at', $date))
            ->when($wss, fn ($qx) => $qx->whereHas('waterScheme', fn ($w) => $w->where('name', 'like', "%{$wss}%")))
            ->orderByDesc('sampled_at')
            ->limit(200)
            ->get();

        $rows = $samples->map(function ($s) {
            $cs = (int) ($s->current_status instanceof \BackedEnum ? $s->current_status->value : $s->current_status);
            $resultLbl = match ($cs) {
                WaterSampleCurrentStatusEnum::FIT->value   => 'Fit',
                WaterSampleCurrentStatusEnum::UNFIT->value => 'Unfit',
                default                                    => 'Pending',
            };
            return [
                'id'           => $s->id,
                'slug'         => $s->slug,
                'wss_name'     => $s->waterScheme?->name ?? '—',
                'district'     => $s->district?->name ?? '—',
                'sampled_at'   => $s->getRawOriginal('sampled_at'),
                'result'       => $resultLbl,
                'cause'        => $resultLbl === 'Unfit' ? ($s->remarks ?: '—') : null,
            ];
        });

        if ($result) $rows = $rows->where('result', $result)->values();

        return response()->json([
            'rows'      => $rows->values(),
            'districts' => District::query()
                ->whereIn('id', $this->districtIds())
                ->orderBy('name')->get(['id','name']),
        ]);
    }

    public function isrShow(int $id): JsonResponse
    {
        $phedIds = $this->phedDivisionIds();
        $sample = WaterSample::query()
            ->with(['waterScheme:id,name', 'phedDivision:id,name', 'district:id,name', 'tests' => fn ($q) => $q->orderBy('round'), 'waterSampleDetails.test:id,water_quality_parameter,unit,who_guideline_start,who_guideline_end'])
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->findOrFail($id);

        $cs = (int) ($sample->current_status instanceof \BackedEnum ? $sample->current_status->value : $sample->current_status);
        $resultLbl = match ($cs) {
            WaterSampleCurrentStatusEnum::FIT->value   => 'Fit',
            WaterSampleCurrentStatusEnum::UNFIT->value => 'Unfit',
            default                                    => '—',
        };

        return response()->json([
            'id'           => $sample->id,
            'slug'         => $sample->slug,
            'wss_name'     => $sample->waterScheme?->name ?? '—',
            'district'     => $sample->district?->name ?? '—',
            'phed_division'=> $sample->phedDivision?->name ?? '—',
            'sampled_at'   => $sample->getRawOriginal('sampled_at'),
            'analyzed_at'  => $sample->getRawOriginal('analyzed_at'),
            'reported_at'  => $sample->getRawOriginal('reported_at'),
            'sampling_point' => $sample->sampling_point?->value ?? $sample->sampling_point ?? '—',
            'source_type'  => $sample->source_type?->value ?? $sample->source_type ?? '—',
            'type'         => $sample->test_type?->value ?? $sample->test_type ?? '—',
            'result'       => $resultLbl,
            'remarks'      => $sample->remarks,
            'parameters'   => $sample->waterSampleDetails->map(function ($d) {
                return [
                    'parameter' => $d->test?->water_quality_parameter ?? '—',
                    'unit'      => $d->test?->unit ?? '—',
                    'value'     => $d->analysis_result ?? $d->input_result ?? '—',
                    'range'     => trim(($d->test?->who_guideline_start ?? '') . ' – ' . ($d->test?->who_guideline_end ?? ''), ' –'),
                ];
            })->values(),
            'tests' => $sample->tests->map(fn ($t) => [
                'round' => $t->round,
                'sampled_at' => $t->getRawOriginal('sampled_at'),
                'analyzed_at' => $t->getRawOriginal('analyzed_at'),
                'status' => $t->status?->label() ?? '—',
                'result' => $t->result?->label() ?? '—',
            ])->values(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/wss-register
     |──────────────────────────────────────────────────────────────────*/
    public function wssRegister(Request $request): JsonResponse
    {
        $user = auth()->user();
        $phedIds = $this->phedDivisionIds();

        $q          = $request->query('q');
        $districtId = $request->query('district_id');
        $result     = $request->query('result');
        $schedStatus = $request->query('schedule');

        $schemes = WaterScheme::query()
            ->with(['district:id,name', 'phedDivision:id,name'])
            ->when(!empty($phedIds), fn ($qx) => $qx->whereIn('phed_division_id', $phedIds))
            ->when($q, fn ($qx) => $qx->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")->orWhere('address', 'like', "%{$q}%");
            }))
            ->when($districtId, fn ($qx) => $qx->where('district_id', $districtId))
            ->orderBy('name')
            ->get();

        $schemeIds = $schemes->pluck('id');
        $latest = DB::table('water_samples')
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

        $rows = $schemes->map(function ($w) use ($latest, $resultPerScheme) {
            $info = $latest[$w->id] ?? null;
            $last = $resultPerScheme[$w->id] ?? null;
            $resultLbl = $this->resolveResult($last);

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
                'phe_division'   => $w->phedDivision?->name ?? '—',
                'source_type'    => $w->source_type ?? 'Tube Well',
                'power_input'    => $w->power_input,
                'operational_status' => $w->is_active ? 'Operational' : 'Inactive',
                'times_tested'   => (int) ($info->times_tested ?? 0),
                'last_result'    => $resultLbl,
                'last_sampled_at'=> $info?->last_sampled_at,
                'next_scheduled' => $nextScheduled,
                'overdue'        => $overdue,
                'last_sample_id' => $last?->id,
            ];
        });

        if ($result) $rows = $rows->where('last_result', $result)->values();
        if ($schedStatus === 'overdue')  $rows = $rows->where('overdue', true)->values();
        if ($schedStatus === 'scheduled')$rows = $rows->where('overdue', false)->values();

        $stats = [
            'total'      => $rows->count(),
            'last_fit'   => $rows->where('last_result', 'Fit')->count(),
            'last_unfit' => $rows->where('last_result', 'Unfit')->count(),
            'untested'   => $rows->where('last_result', 'Untested')->count(),
            'overdue'    => $rows->where('overdue', true)->count(),
        ];

        $grouped = [];
        foreach ($rows->groupBy('district') as $d => $items) {
            $grouped[] = ['district' => $d, 'rows' => $items->values()->all()];
        }

        return response()->json([
            'scope' => [
                'circle'    => $user->circle?->name ?? '—',
                'districts' => District::where('circle_id', $user->circle_id)->orderBy('name')->pluck('name')->all(),
            ],
            'stats'     => $stats,
            'groups'    => $grouped,
            'districts' => District::query()
                ->whereIn('id', $this->districtIds())
                ->orderBy('name')->get(['id','name']),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/notifications  — the SE user's personal bell feed
     |
     |  Returns { items, count, total } matching the XEN shape so the layout
     |  bell + dropdown can share the same component code path. `count` is
     |  unread only; `items` includes read history so the dropdown can show
     |  context. Filtered by notifiable_id = current user (Spatie's
     |  per-user notification routing) so each SE only sees their own.
     |──────────────────────────────────────────────────────────────────*/
    public function notifications(Request $request): JsonResponse
    {
        $user = auth()->user();

        $rows = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', \App\Models\User::class)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $items = $rows->map(function ($n) {
            $data = is_string($n->data ?? '') ? json_decode($n->data, true) : ($n->data ?? []);
            $sample = $n->water_sample_id ? WaterSample::find($n->water_sample_id) : null;
            $kind = match ($n->type_key) {
                'SAMPLE_UNFIT'             => 'Unfit',
                'RETEST_REQUESTED'         => 'Retest',
                'FATE_DECISION_REQUESTED'  => 'Escalation',
                'ESCALATION'               => 'Escalation',
                default                    => 'Update',
            };
            return [
                'id'          => $n->id,
                'sample_slug' => $sample?->slug ?? ($data['sample_slug'] ?? null),
                'sample_id'   => $n->water_sample_id ?? ($data['sample_id'] ?? null),
                'kind'        => $kind,
                'created_at'  => $n->created_at,
                'due_at'      => $n->due_at,
                'read_at'     => $n->read_at,
                'message'     => $data['message'] ?? null,
            ];
        });

        return response()->json([
            'items' => $items,
            'count' => $rows->whereNull('read_at')->count(),
            'total' => $items->count(),
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/samples/{id}/trail  — full trail for the SeTrailModal
     |  Mirrors XenDashboardController::trailDetail but scoped by circle.
     |──────────────────────────────────────────────────────────────────*/
    public function trailDetail(int $id): JsonResponse
    {
        $user = auth()->user()->load(['circle', 'district']);
        $phedIds = $this->phedDivisionIds();

        $sample = WaterSample::query()
            ->with([
                'waterScheme:id,name',
                'phedDivision:id,name',
                'district:id,name',
                'tests' => fn ($q) => $q->orderBy('round'),
            ])
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->findOrFail($id);

        // Build timeline: tests, actions, notifications — sorted chronologically.
        $timeline = [];
        foreach ($sample->tests as $t) {
            $title = $t->round == 0 ? 'Unfit Result Recorded' : 'Retest R' . $t->round . ' Analyzed';
            $timeline[] = [
                'type'    => 'test',
                'title'   => $t->round == 0 ? 'Unfit Result Recorded' : $title,
                'details' => $t->round == 0 ? 'Initial sample tested.' : 'Sample registered / analyzed.',
                'date'    => $t->getRawOriginal('analyzed_at') ?: $t->getRawOriginal('created_at'),
                'user'    => 'Lab',
                'round'   => $t->round,
            ];
        }
        foreach (DB::table('water_sample_actions as a')->leftJoin('users as u', 'a.user_id', '=', 'u.id')->where('a.water_sample_id', $sample->id)->select('a.*', 'u.name as user_name')->get() as $a) {
            $timeline[] = [
                'type'    => 'action',
                'title'   => $a->action_type . ' R' . $a->round,
                'details' => $a->details,
                'date'    => $a->action_date ?: $a->created_at,
                'user'    => $a->user_name ?? 'System',
                'round'   => $a->round,
            ];
        }
        foreach (DB::table('notifications')->where('water_sample_id', $sample->id)->orderBy('created_at')->get() as $n) {
            $data = json_decode($n->data ?? '{}', true);
            $timeline[] = [
                'type'    => 'notification',
                'title'   => 'Notification Sent',
                'details' => $data['message'] ?? $data['content'] ?? 'Notification',
                'date'    => $n->created_at,
                'user'    => 'System',
                'round'   => $data['round'] ?? null,
            ];
        }
        usort($timeline, fn ($a, $b) => strtotime($a['date']) <=> strtotime($b['date']));

        $notifs = DB::table('notifications as n')
            ->leftJoin('users as u', 'n.notifiable_id', '=', 'u.id')
            ->where('n.water_sample_id', $sample->id)
            ->orderBy('n.created_at')
            ->select('n.id', 'n.created_at', 'n.type_key', 'n.data', 'n.action_taken_at', 'u.name as user_name')
            ->get()
            ->map(function ($n) {
                $data = is_string($n->data ?? '') ? json_decode($n->data, true) : ($n->data ?? []);
                return [
                    'id'         => $n->id,
                    'created_at' => $n->created_at,
                    'type_key'   => $n->type_key,
                    'message'    => $data['message'] ?? null,
                    'recipient'  => $n->user_name,
                    'status'     => $n->action_taken_at ? 'Acknowledged' : 'Initial',
                ];
            });

        $unfitTest = $sample->tests->first(function ($t) {
            $r = $t->result instanceof \BackedEnum ? $t->result->value : $t->result;
            return (int) $r === WaterSampleTestResultEnum::UNFIT->value;
        });
        $causeText = $unfitTest?->remarks ?: 'Lab Test';

        return response()->json([
            'id'         => $sample->id,
            'slug'       => $sample->slug,
            'timeline'   => $timeline,
            'sample_info' => [
                'sample_id'     => $sample->slug,
                'wss'           => $sample->waterScheme?->name ?? '—',
                'phed_division' => $sample->phedDivision?->name ?? '—',
                'district'      => $sample->district?->name ?? '—',
                'se_name'       => $user->name ?? '—',
                'cause'         => $causeText,
            ],
            'notifications_panel' => $notifs,
            'action_types'        => [
                'Chlorination Done',
                'Source Cleaned',
                'Inspected',
                'Maintenance Done',
                'Operator Trained',
                'Source Replaced',
                'Retest Requested',
                'Other',
            ],
        ]);
    }

    /* ──────────────────────────────────────────────────────────────────
     |  /se/actions/request-retest  — log an action + flag retest
     |──────────────────────────────────────────────────────────────────*/
    public function requestRetest(Request $request): JsonResponse
    {
        $request->validate([
            'water_sample_id' => 'required|exists:water_samples,id',
            'action_type'     => 'required|string',
            'details'         => 'nullable|string',
            'action_date'     => 'nullable|date',
        ]);

        $phedIds = $this->phedDivisionIds();
        $sample = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->findOrFail($request->water_sample_id);

        DB::beginTransaction();
        try {
            $latestTest = WaterSampleTest::where('water_sample_id', $sample->id)->orderByDesc('round')->first();
            $action = \App\Models\WaterSamples\WaterSampleAction::create([
                'water_sample_id' => $sample->id,
                'user_id'         => auth()->id(),
                'round'           => $latestTest?->round ?? 0,
                'action_type'     => $request->action_type,
                'details'         => $request->details,
                'action_date'     => $request->action_date ?? now()->toDateString(),
            ]);

            // Close out any open SAMPLE_UNFIT notification
            DB::table('notifications')
                ->where('water_sample_id', $sample->id)
                ->where('type_key', 'SAMPLE_UNFIT')
                ->whereNull('action_taken_at')
                ->update(['action_taken_at' => now(), 'status' => 2]);

            // Notify the lab/creator that a retest is requested
            DB::table('notifications')->insert([
                'id'              => \Illuminate\Support\Str::uuid(),
                'type'            => 'App\\Notifications\\RetestRequested',
                'notifiable_type' => \App\Models\User::class,
                'notifiable_id'   => $sample->created_by,
                'data'            => json_encode([
                    'message' => 'SE has requested a retest for sample #' . $sample->slug,
                ]),
                'created_at'      => now(),
                'updated_at'      => now(),
                'water_sample_id' => $sample->id,
                'round'           => ($latestTest?->round ?? 0) + 1,
                'role'            => 'STAFF',
                'status'          => 1,
                'type_key'        => 'RETEST_REQUESTED',
                'notified_at'     => now(),
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Action logged and retest requested successfully',
                'action'  => $action,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /* ──────────────────────────────────────────────────────────────────
     |  Builders / helpers
     |──────────────────────────────────────────────────────────────────*/

    /**
     * Build the unfit-samples list with action-stage labels per row.
     * Stage rules (mirror the XEN flow):
     *   No Action      — sample is UNFIT, no retest yet, no action logged
     *   Action Taken   — sample is UNFIT and an action row exists
     *   Re-notified    — round >= 2 (still unfit after a retest)
     */
    private function buildUnfitSamples(array $phedIds, int $limit = 50, ?string $q = null, ?string $status = null): array
    {
        $samples = WaterSample::query()
            ->with(['waterScheme:id,name', 'district:id,name'])
            ->when(!empty($phedIds), fn ($x) => $x->whereIn('phed_division_id', $phedIds))
            ->where('current_status', WaterSampleCurrentStatusEnum::UNFIT->value)
            ->when($q, fn ($x) => $x->where(function ($w) use ($q) {
                $w->where('slug', 'like', "%{$q}%")
                  ->orWhereHas('waterScheme', fn ($s) => $s->where('name', 'like', "%{$q}%"));
            }))
            ->orderBy('district_id')
            ->orderByDesc('sampled_at')
            ->limit($limit)
            ->get();

        $actionSampleIds = DB::table('water_sample_actions')
            ->whereIn('water_sample_id', $samples->pluck('id'))
            ->pluck('water_sample_id')
            ->all();
        $actionSet = array_flip($actionSampleIds);

        $rows = $samples->map(function ($s) use ($actionSet) {
            $round = (int) $s->current_round;
            $hasAction = isset($actionSet[$s->id]);
            if ($round >= 2)        $stage = 'Re-notified';
            elseif ($hasAction)     $stage = 'Action Taken';
            else                    $stage = 'No Action';

            return [
                'id'         => $s->id,
                'slug'       => $s->slug,
                'wss_name'   => $s->waterScheme?->name ?? '—',
                'district'   => $s->district?->name ?? '—',
                'sampled_at' => $s->getRawOriginal('sampled_at'),
                'cause'      => 'Biological',
                'parameter'  => $s->remarks ?: '—',
                'status'     => $stage,
                'round'      => $round,
                'stage'      => $round > 0 ? 'R' . $round : '—',
            ];
        })->all();

        if ($status) {
            $needle = strtolower($status);
            $rows = array_values(array_filter($rows, fn ($r) => strtolower($r['status']) === $needle));
        }

        return $rows;
    }

    private function buildRetestSamples(array $phedIds, int $limit = 10): array
    {
        $tests = WaterSampleTest::query()
            ->with(['waterSample.waterScheme:id,name', 'waterSample.district:id,name'])
            ->join('water_samples as ws', 'water_sample_tests.water_sample_id', '=', 'ws.id')
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('ws.phed_division_id', $phedIds))
            ->where('water_sample_tests.round', '>', 0)
            ->orderByDesc('water_sample_tests.created_at')
            ->limit($limit)
            ->select('water_sample_tests.*')
            ->get();

        return $tests->map(function ($t) {
            $statusVal = $t->status instanceof \BackedEnum ? $t->status->value : $t->status;
            $statusLabel = match ((int) $statusVal) {
                WaterSampleTestStatusEnum::PENDING->value     => 'Awaiting',
                WaterSampleTestStatusEnum::IN_PROGRESS->value => 'In Analysis',
                WaterSampleTestStatusEnum::COMPLETED->value   => 'Analysed',
                default                                       => 'Pending',
            };
            return [
                'id'             => $t->id,
                'retest_slug'    => $t->waterSample?->slug ? $t->waterSample->slug . ' R' . $t->round : '—',
                'original_slug'  => $t->waterSample?->slug ?? '—',
                'wss_name'       => $t->waterSample?->waterScheme?->name ?? '—',
                'stage'          => 'R' . $t->round,
                'date'           => $t->getRawOriginal('sampled_at'),
                'status'         => $statusLabel,
            ];
        })->all();
    }

    private function overdueWss(array $phedIds, int $limit = 6): array
    {
        $schemes = WaterScheme::query()
            ->with(['district:id,name'])
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->get();

        $latest = DB::table('water_samples')
            ->whereIn('water_scheme_id', $schemes->pluck('id'))
            ->whereNull('deleted_at')
            ->select('water_scheme_id', DB::raw('MAX(sampled_at) as last_sampled_at'))
            ->groupBy('water_scheme_id')
            ->get()
            ->keyBy('water_scheme_id');

        $overdue = $schemes->map(function ($w) use ($latest) {
            $info = $latest[$w->id] ?? null;
            $days = null;
            $nextDt = null;
            if ($info?->last_sampled_at) {
                $nextDt = Carbon::parse($info->last_sampled_at)->addMonths(3);
                if ($nextDt->isPast()) {
                    $days = (int) abs($nextDt->diffInDays(now()));
                }
            }
            return [
                'id'              => $w->id,
                'wss_name'        => $w->name,
                'district'        => $w->district?->name ?? '—',
                'last_sampled_at' => $info?->last_sampled_at,
                'next_scheduled'  => $nextDt?->toDateString(),
                'days_overdue'    => $days,
            ];
        })
        ->filter(fn ($r) => $r['days_overdue'] !== null)
        ->sortByDesc('days_overdue')
        ->values();

        return [
            'count' => $overdue->count(),
            'rows'  => $overdue->take($limit)->all(),
        ];
    }

    private function notificationsPanel(array $phedIds, int $days = 7, int $limit = 6): array
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
                    'SAMPLE_UNFIT'          => 'Unfit',
                    'RETEST_REQUESTED'      => 'Retest',
                    'FATE_DECISION_REQUESTED' => 'Escalation',
                    default                  => 'Notice',
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

    private function buildMonthlyAbstract(array $phedIds, ?string $fromDate, ?string $toDate): array
    {
        // Default to FY (Jul..Mar) window if no filter
        $endDt = $toDate ? Carbon::parse($toDate) : now();
        $startDt = $fromDate ? Carbon::parse($fromDate) : $endDt->copy()->subMonths(8)->startOfMonth();

        // Build month buckets
        $months = [];
        $cursor = $startDt->copy()->startOfMonth();
        while ($cursor <= $endDt) {
            $months[] = $cursor->copy();
            $cursor->addMonth();
        }

        // Pull aggregates per (district, year-month)
        $stats = WaterSample::query()
            ->when(!empty($phedIds), fn ($q) => $q->whereIn('phed_division_id', $phedIds))
            ->whereBetween('sampled_at', [$startDt->startOfMonth(), $endDt->endOfMonth()])
            ->select(
                'district_id',
                DB::raw('DATE_FORMAT(sampled_at, "%Y-%m") as ym'),
                DB::raw('COUNT(*) as tested'),
                DB::raw('SUM(CASE WHEN current_status = ' . WaterSampleCurrentStatusEnum::FIT->value . ' THEN 1 ELSE 0 END) as fit'),
                DB::raw('SUM(CASE WHEN current_status = ' . WaterSampleCurrentStatusEnum::UNFIT->value . ' THEN 1 ELSE 0 END) as unfit')
            )
            ->groupBy('district_id', 'ym')
            ->get();

        $districtsList = District::query()
            ->whereIn('id', $this->districtIds())
            ->orderBy('name')
            ->get(['id', 'name']);

        $rows = $districtsList->map(function ($d) use ($months, $stats, $phedIds) {
            $cells = [];
            foreach ($months as $m) {
                $ym = $m->format('Y-m');
                $s = $stats->first(fn ($x) => (int) $x->district_id === (int) $d->id && $x->ym === $ym);
                $cells[] = [
                    'ym'   => $ym,
                    'label'=> $m->format('M'),
                    'tested' => (int) ($s->tested ?? 0),
                    'fit'    => (int) ($s->fit ?? 0),
                    'unfit'  => (int) ($s->unfit ?? 0),
                ];
            }
            $phedDiv = PhedDivision::where('district_id', $d->id)->first();
            return [
                'district_id'  => $d->id,
                'district'     => $d->name,
                'phe_division' => $phedDiv?->name ?? '—',
                'cells'        => $cells,
                'totals'       => [
                    'tested' => array_sum(array_column($cells, 'tested')),
                    'fit'    => array_sum(array_column($cells, 'fit')),
                    'unfit'  => array_sum(array_column($cells, 'unfit')),
                ],
            ];
        });

        return [
            'months' => array_map(fn ($m) => ['ym' => $m->format('Y-m'), 'label' => $m->format('M')], $months),
            'rows'   => $rows->values()->all(),
        ];
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
        if ($cs === WaterSampleCurrentStatusEnum::FIT->value)   return 'Fit';
        if ($cs === WaterSampleCurrentStatusEnum::UNFIT->value) return 'Unfit';
        if ($cs === WaterSampleCurrentStatusEnum::CLOSED->value) return 'Fit';
        return 'Untested';
    }
}
