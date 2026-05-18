<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintStatusEnum;
use App\Enums\InventoryStatusEnum;
use App\Enums\IssueStatusEnum;
use App\Enums\TestTypeEnum;
use App\Enums\WaterSampleResultEnum;
use App\Http\Requests\DashboardRequest;
use App\Models\Asset\AssetMaintenanceLog;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\Complaint;
use App\Models\District;
use App\Models\Inventory\Inventory;
use App\Models\Issues\Issue;
use App\Models\KpiLabPeriod;
use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Models\AuditInspection;
use App\Models\PtRoundResult;
use App\Models\StaffTraining;
use App\Models\VerificationVisit;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterScheme;
use App\Services\DashboardService;
use App\Traits\DashboardFilterTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DashboardController extends Controller
{
    use DashboardFilterTrait;

    private int $index;
    private $authUser;


    // Renamed semantic: this is now "true for any role that bypasses
    // data scoping" (SA + system-manager + view-only-admin + general-view-account).
    // The legacy property name is kept for callers/blame churn.
    private bool $isSystemAdministrator;

    public function __construct()
    {
        $this->index = 0;
        $this->middleware(function ($request, $next) {
            $this->authUser = auth()->user();
            $this->isSystemAdministrator = $this->authUser->isUnscoped();
            return $next($request);
        });
    }

    /**
     * Roles whose dashboard view should be scoped by the lab(s) they're
     * attached to via the laboratory_user pivot. Hierarchy roles (CE / SE /
     * XEN / Secretary) are deliberately excluded — they have their own
     * portals (XenDashboardController etc.) where region/circle/division
     * scoping lives. If they land on the main dashboard, they fall back to
     * the unscoped behavior rather than getting the wrong (lab-pivot) scope.
     */
    private const LAB_SCOPED_ROLES = [
        'lab-incharge',
        'laboratory-assistant',
        'junior-clerk',
    ];

    /**
     * Lab IDs the current user is attached to (laboratory_user pivot).
     * Returns null = "no filter, see everything" for:
     *   - unscoped roles (SA / manager / view-only / general-view)
     *   - hierarchy roles (CE / SE / XEN / Secretary) — they have their own
     *     portals where the correct scope (region/circle/division) lives;
     *     on this dashboard they fall back to the pre-RBAC unscoped view.
     * Returns [0] for lab-tier roles with no lab attached so callers'
     * whereIn() matches nothing (safer than degenerating to unscoped).
     */
    private function userLabIds(): ?array
    {
        if ($this->isSystemAdministrator) {
            return null;
        }
        if (!$this->authUser?->hasAnyRole(self::LAB_SCOPED_ROLES)) {
            return null;
        }
        $ids = $this->authUser
            ?->laboratories()
            ->pluck('laboratories.id')
            ->toArray() ?? [];
        return $ids ?: [0];
    }

    /**
     * District IDs covered by the user's lab(s). Used to scope WSS counts
     * since WSS belong to districts, not labs directly.
     *
     * Resolution chain (first non-empty wins):
     *   1. district_laboratory pivot — canonical "declared catchment"
     *   2. Distinct district_ids from samples this lab has processed —
     *      implicit catchment, survives the pivot being unseeded (which it
     *      currently is across all 9 labs in this DB)
     *   3. The user's home district — last-ditch fallback
     *
     * Returns null = "no filter" for unscoped + hierarchy roles.
     */
    private function userDistrictIds(): ?array
    {
        if ($this->isSystemAdministrator) {
            return null;
        }
        if (!$this->authUser?->hasAnyRole(self::LAB_SCOPED_ROLES)) {
            return null;
        }
        $labIds = $this->userLabIds();
        if (empty($labIds) || $labIds === [0]) {
            return [0];
        }

        // 1. Canonical pivot
        $ids = DB::table('district_laboratory')
            ->whereIn('laboratory_id', $labIds)
            ->distinct()
            ->pluck('district_id')
            ->toArray();

        // 2. Fallback: districts inferred from actual samples this lab
        //    has processed. Survives the pivot being empty in dev/test DB.
        if (empty($ids)) {
            $ids = DB::table('water_samples')
                ->whereIn('laboratory_id', $labIds)
                ->whereNotNull('district_id')
                ->distinct()
                ->pluck('district_id')
                ->toArray();
        }

        // 3. Final fallback: user's home district
        if (empty($ids) && $this->authUser?->district_id) {
            $ids = [$this->authUser->district_id];
        }

        return $ids ?: [0];
    }

    /**
     * Handle the incoming request.
     *
     * @param DashboardRequest $request
     * @return JsonResponse
     */
    public function __invoke(DashboardRequest $request): JsonResponse
    {
        $dashboardService = new DashboardService($request);

        $waterSamples = $this->getWaterSamplesCount($request);
        $laboratories = $dashboardService->getLaboratoryCoverage();
        $percentageWaterSamplesCollectedFrom = $dashboardService->getPercentageWaterSamplesCollectedFrom();
        $testedWaterSamples = $dashboardService->getTestedWaterSamples();
        $privateWaterSamples = $dashboardService->getPrivateWaterSamples();
        $onDemandTestWaterSamples = $dashboardService->getOnDemandTestWaterSamples();
        $physicalWaterSamples = $dashboardService->getPhysicalWaterSamples();
        $chemicalWaterSamples = $dashboardService->getChemicalWaterSamples();
        $microBialWaterSamples = $dashboardService->getMicroBialWaterSamples();
        $percentageWaterSchemesFitUnit = $dashboardService->getWaterSchemesFitUnfit();

        $laboratoryMaterialsAvailability = $dashboardService->getLaboratoryMaterialsAvailability();
        $laboratoriesWaterSampleResults = $dashboardService->getLaboratoriesWaterSampleResults();
        $associatedWaterSchemes = $dashboardService->getAssociatedWaterSchemes();
        $laboratoryWiseTotalTestedWaterSamples = $dashboardService->getLaboratoryWiseTotalTestedWaterSamples();
        $laboratoryWiseRevenue = $dashboardService->getLaboratoryWiseRevenue();
        $laboratoryWiseInventoryRequests = $dashboardService->getLaboratoryWiseInventoryRequests();
        $monthlyWaterSchemesTestingCount = $dashboardService->getMonthlyWaterSchemesTestingCount();
        $monthlyWaterSampleFitUnfit = $dashboardService->getMonthlyWaterSampleFitUnfit();
//        $districtWiseContaminantsCount = $dashboardService->getDistrictWiseContaminantsCount();


        // RBAC: scoped roles see only their own lab(s) in any "lab count"
        // surface (the "Labs: N" / "N Labs total" pills on the cards).
        $userLabIds = $this->userLabIds();
        $totalLaboratories = Laboratory::query()
            ->when(isset($request->division_id), fn(Builder $query) => $query->where('division_id', '=', $request->division_id))
            ->when(isset($request->district_id), fn(Builder $query) => $query->where('district_id', '=', $request->district_id))
            ->when($userLabIds !== null, fn(Builder $query) => $query->whereIn('id', $userLabIds))
            ->count();

        // WSS count is scoped to the union of districts covered by the user's
        // lab(s) — not just users.district_id, which only covers the user's
        // home district and missed multi-district lab catchments.
        $userDistrictIds = $this->userDistrictIds();
        $totalWaterSchemes = WaterScheme::query()
            ->when(isset($request->division_id), fn(Builder $query) => $query->where('division_id', '=', $request->division_id))
            ->when(isset($request->district_id), fn(Builder $query) => $query->where('district_id', '=', $request->district_id))
            ->when($userDistrictIds !== null, fn(Builder $query) => $query->whereIn('district_id', $userDistrictIds))
            ->count();


        $districtsWaterSampleResults = $this->getDistrictWiseWaterSampleResults();

        $totalComplaints = $this->getTotalComplaints();
        $totalIssues = $this->getTotalIssues();

        $waterSchemesStatus = $this->getWaterSchemeStatuCount($request);

        // "Pending" from a user perspective means anything still awaiting full
        // fulfillment — that's both `pending` (untouched) and `partially_approved`
        // (some items issued, rest still waiting). Also fixed: comparison now
        // uses ->value so the string match is reliable across Laravel versions.
        $totalPendingInventoryRequests = Inventory::query()
            ->when(!$this->isSystemAdministrator, fn(Builder $query) => $query->where('laboratory_id', '=', $this->authUser?->laboratoryUser?->id))
            ->whereIn('status', [
                InventoryStatusEnum::PENDING->value,
                InventoryStatusEnum::PARTIALLY_APPROVED->value,
            ])
            ->count();

        // RBAC: every WSS-breakdown query below scopes to the user's covered
        // districts so the WSS Status row (Operational / Non-Op / Abandoned /
        // WIP / Total) stays consistent with the Total WSS count above.
        // Previously these used withoutGlobalScope() with no extra filtering,
        // which produced the "Operational=3 vs Total=1" inconsistency for
        // lab-incharge users on the production dashboard.
        $scopeWss = function (Builder $query) use ($userDistrictIds) {
            if ($userDistrictIds !== null) {
                $query->whereIn('district_id', $userDistrictIds);
            }
            return $query;
        };

        $operationWise = WaterScheme::query()
            ->select('operation')
            ->whereIn('operation', ['Operational', 'Non-Operational', 'Work in progress', 'Abandoned'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->groupBy('operation')
            ->get()
            ->mapWithKeys(function ($operation) {
                return [$operation->operation => $operation->count];
            })->toArray();


        $chamberWise = WaterScheme::query()
            ->select('chamber')
            ->whereIn('chamber', ['Satisfactory', 'Good', 'Worst'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->groupBy('chamber')
            ->get()
            ->mapWithKeys(function ($chamber) {
                return [$chamber->chamber => $chamber->count];
            })->toArray();

        $totalSourceCount = WaterScheme::query()
            ->whereIn('source_type', ['Gravity', 'Surface Water'])
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->count();

        $source = WaterScheme::query()
            ->select('source_type')
            ->whereIn('source_type', ['Gravity', 'Surface Water'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->groupBy('source_type')
            ->get()
            ->mapWithKeys(function ($source) use ($totalSourceCount) {
                $percentage = $totalSourceCount > 0 ? ($source->count / $totalSourceCount) * 100 : 0;
                return [$source->source_type => round($percentage, 1)];
            });

        $sourceTypeChart = $dashboardService->getPieChart($source);

        $totalOperationalCount = WaterScheme::query()
            ->whereIn('operation', ['Operational', 'Non-Operational'])
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->count();

        $operation = WaterScheme::query()
            ->select('operation')
            ->whereIn('operation', ['Operational', 'Non-Operational'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->groupBy('operation')
            ->get()
            ->mapWithKeys(function ($operation) use ($totalOperationalCount) {
                $percentage = $totalOperationalCount > 0 ? ($operation->count / $totalOperationalCount) * 100 : 0;
                return [$operation->operation => round($percentage, 1)];
            });

        $operationalChart = $dashboardService->getPieChart($operation);

        $totalPowerInputCount = WaterScheme::query()
            ->whereIn('power_input', ['Wapda', 'Solar'])
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->count();

        $powerInput = WaterScheme::query()
            ->select('power_input')
            ->whereIn('power_input', ['Wapda', 'Solar'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->tap($scopeWss)
            ->groupBy('power_input')
            ->get()
            ->mapWithKeys(function ($powerInput) use ($totalPowerInputCount) {
                $percentage = $totalPowerInputCount > 0 ? ($powerInput->count / $totalPowerInputCount) * 100 : 0;
                return [$powerInput->power_input->value => round($percentage, 1)];
            });

        $powerInputChart = $dashboardService->getPieChart($powerInput);


        return response()->json([
            'data' => [
                'water_schemes_status' => $waterSchemesStatus,
                'water_samples' => $waterSamples,
                'total_laboratories' => $totalLaboratories,
                'total_water_schemes' => $totalWaterSchemes,
                'districts_water_sample_results' => $districtsWaterSampleResults,
                'total_complaints' => $totalComplaints,
                'total_issues' => $totalIssues,
                'total_pending_inventory_requests' => $totalPendingInventoryRequests,

                'laboratories' => $laboratories,
                'percentage_water_samples_collected_from' => $percentageWaterSamplesCollectedFrom,
                'tested_water_samples' => $testedWaterSamples,
                'private_water_samples' => $privateWaterSamples,
                'on_demand_test_water_samples' => $onDemandTestWaterSamples,
                'physical_parameter_water_samples' => $physicalWaterSamples,
                'chemical_parameter_water_samples' => $chemicalWaterSamples,
                'microbial_water_samples' => $microBialWaterSamples,
                'percentage_water_schemes_fit_unit' => $percentageWaterSchemesFitUnit,
                'laboratory_wise_revenue' => $laboratoryWiseRevenue,
                // F-07 / F-06 — surface the Total Revenue and Pending Revenue
                // cards directly so the dashboard doesn't need to recompute.
                'total_revenue'           => $dashboardService->getTotalRevenue(),
                'pending_revenue'         => $dashboardService->getPendingRevenue(),
                'laboratory_wise_inventory_requests' => $laboratoryWiseInventoryRequests,
                'laboratories_water_sample_results' => $laboratoriesWaterSampleResults,
                'laboratory_wise_total_tested_water_samples' => $laboratoryWiseTotalTestedWaterSamples,
                'laboratory_materials_availability' => $laboratoryMaterialsAvailability,
                'monthly_water_sample_fit_unfit' => $monthlyWaterSampleFitUnfit,
                'associated_water_schemes' => $associatedWaterSchemes,
//                'district_wise_contaminants_count' => $districtWiseContaminantsCount,
                'monthly_water_schemes_testing_count' => $monthlyWaterSchemesTestingCount,
                'source_type_chart' => $sourceTypeChart,
                'operational_chart' => $operationalChart,
                'power_input_chart' => $powerInputChart,
                'operation_wise_graph' => [
                    'labels' => array_keys($operationWise),
                    'datasets' => [
                        [
                            'label' => 'Water Supply Schemes Operation-Wise',
                            'data' => array_values($operationWise),
                            'backgroundColor' => ['#4caf50', '#ff4032'],
                        ]
                    ]
                ],
                'chamber_wise_graph' => [
                    'labels' => array_keys($chamberWise),
                    'datasets' => [
                        [
                            'label' => 'Water Supply Schemes Operation-Wise',
                            'data' => array_values($chamberWise),
                            'backgroundColor' => ['#fbc02d', '#4caf50', '#ff4032'],
                        ]
                    ]
                ]
            ]
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * District-wise heatmap data filtered by parameter type / sub-parameter.
     *
     * Accepted body params (all optional):
     *   parameter_type:           'physical' | 'chemical' | 'microbial' | null/'overall'
     *   water_quality_parameter:  e.g. 'Arsenic', 'E. coli' — applied to tests.water_quality_parameter
     *
     * Honors all standard dashboard filters (region/division/district/PHE division/laboratory/duration/type).
     *
     * For each district returns:
     *   - aggregate counts under the active parameter_type/sub-parameter selection
     *   - a `by_type` breakdown (microbial / chemical / physical) computed WITHOUT
     *     the heatmap-specific filters, so the detail panel always shows the full
     *     picture for the district regardless of which dropdown is active.
     *     A single water_sample can have multiple test types, so the per-type
     *     counts can sum to more than `tested` — that's by design.
     */
    public function districtHeatmap(DashboardRequest $request): JsonResponse
    {
        $parameterType = strtolower((string) $request->input('parameter_type', ''));
        $parameterName = $request->input('water_quality_parameter');

        $typeMap = [
            'physical'  => [TestTypeEnum::PHYSICAL->value],
            'chemical'  => [TestTypeEnum::CHEMICAL->value],
            'microbial' => [
                TestTypeEnum::Microbiological_Kit->value,
                TestTypeEnum::Microbiological_Medical->value,
            ],
        ];
        $types = $typeMap[$parameterType] ?? null;

        // Base filters everyone honors (region/duration/PHE-vs-Private/etc.) plus
        // the result-not-null gate.
        $applyBase = function ($query) use ($request) {
            $query->whereNotNull('result')
                ->applyDashboardFilters($request, 'water_samples');
        };

        // Heatmap-aware filters: base + parameter_type + sub-parameter.
        $applyHeatmap = function ($query) use ($applyBase, $types, $parameterName) {
            $applyBase($query);
            if ($types || $parameterName) {
                $query->whereHas('waterSampleDetails.test', function (Builder $q) use ($types, $parameterName) {
                    if ($types) {
                        $q->whereIn('type', $types);
                    }
                    if ($parameterName) {
                        $q->where('water_quality_parameter', $parameterName);
                    }
                });
            }
        };

        // Factory for "samples whose details include at least one test of these types".
        // Used for the by_type breakdown — intentionally bypasses parameter_type so
        // the detail panel shows the full type split regardless of dropdown state.
        $sliceByType = function (array $typeValues, ?array $resultFilter = null) use ($applyBase) {
            return function ($q) use ($applyBase, $typeValues, $resultFilter) {
                $applyBase($q);
                $q->whereHas('waterSampleDetails.test', fn(Builder $qq) => $qq->whereIn('type', $typeValues));
                if ($resultFilter) {
                    $q->whereIn('result', $resultFilter);
                }
            };
        };

        $microTypes = [
            TestTypeEnum::Microbiological_Kit->value,
            TestTypeEnum::Microbiological_Medical->value,
        ];
        $chemTypes  = [TestTypeEnum::CHEMICAL->value];
        $physTypes  = [TestTypeEnum::PHYSICAL->value];

        $districts = District::query()
            ->when(!$this->isSystemAdministrator, fn(Builder $q) => $q->where('id', '=', $this->authUser?->district_id))
            ->select('id', 'name')
            ->withCount([
                // Aggregate counts honoring the active heatmap filter
                'waterSamples as tested' => $applyHeatmap,
                'waterSamples as fit'    => function ($q) use ($applyHeatmap) {
                    $applyHeatmap($q);
                    $q->whereIn('result', ['Fit', '1']);
                },
                'waterSamples as unfit'  => function ($q) use ($applyHeatmap) {
                    $applyHeatmap($q);
                    $q->whereIn('result', ['Unfit', '2']);
                },
                // Per-type breakdown — full sample set, split by test type
                'waterSamples as micro_tested' => $sliceByType($microTypes),
                'waterSamples as micro_unfit'  => $sliceByType($microTypes, ['Unfit', '2']),
                'waterSamples as chem_tested'  => $sliceByType($chemTypes),
                'waterSamples as chem_unfit'   => $sliceByType($chemTypes, ['Unfit', '2']),
                'waterSamples as phys_tested'  => $sliceByType($physTypes),
                'waterSamples as phys_unfit'   => $sliceByType($physTypes, ['Unfit', '2']),
                // WSS coverage — total registered, those with ≥1 sample, and those
                // with ≥1 unfit sample. Lets the panel show real coverage % and
                // an accurate "unfit WSS" KPI instead of conflating samples with
                // schemes.
                'waterSchemes as wss',
                'waterSchemes as tested_wss' => function ($q) use ($applyBase) {
                    $q->whereHas('waterSamples', fn(Builder $qq) => $applyBase($qq));
                },
                'waterSchemes as unfit_wss' => function ($q) use ($applyBase) {
                    $q->whereHas('waterSamples', function (Builder $qq) use ($applyBase) {
                        $applyBase($qq);
                        $qq->whereIn('result', ['Unfit', '2']);
                    });
                },
            ])
            ->get()
            ->map(function ($d) {
                $tested = (int) $d->tested;
                $unfit  = (int) $d->unfit;
                return [
                    'id'         => $d->id,
                    'name'       => $d->name,
                    'wss'        => (int) $d->wss,
                    'tested_wss' => (int) $d->tested_wss,
                    'unfit_wss'  => (int) $d->unfit_wss,
                    'tested'     => $tested,
                    'fit'        => (int) $d->fit,
                    'unfit'      => $unfit,
                    'unfit_pct'  => $tested > 0 ? (int) round(($unfit / $tested) * 100) : null,
                    'by_type'    => [
                        'microbial' => [
                            'tested' => (int) $d->micro_tested,
                            'unfit'  => (int) $d->micro_unfit,
                        ],
                        'chemical'  => [
                            'tested' => (int) $d->chem_tested,
                            'unfit'  => (int) $d->chem_unfit,
                        ],
                        'physical'  => [
                            'tested' => (int) $d->phys_tested,
                            'unfit'  => (int) $d->phys_unfit,
                        ],
                    ],
                ];
            });

        return response()->json([
            'data' => [
                'districts'               => $districts,
                'parameter_type'          => $parameterType ?: 'overall',
                'water_quality_parameter' => $parameterName,
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Lab × KPI performance matrix for CH-05.
     *
     * Computes 5 KPIs from existing schema (002/003/004/005/006). The other 4
     * (001/007/008/009) are merged from dedicated modules (PT, Training
     * Register, Audit Checklist, Verification Log) — or fall back to manual
     * monthly entries in kpi_lab_periods when those modules have no data yet.
     *
     * Definitions (verified against SRS §3.4 KPI matrix on 2026-05-18):
     *   KPI-002 Equipment Calibration   — % of calibration schedules with ≥1 completed log in the period
     *   KPI-003 Retest of Unfit Samples — % of unfit samples that received a XEN action AND have current_round > 1
     *                                     SRS: "Retest module (after XEN action only)"
     *   KPI-004 Monthly Sampling Coverage — % of monthly-target samples actually collected, where
     *                                       monthly target = 15% × WSS count per lab's covered districts.
     *                                       SRS: "15% of WSS count auto-calculated per district"
     *   KPI-005 Turnaround Time         — % of REPORTED samples meeting (reported_at - created_at) ≤ 48h.
     *                                     SRS: "Receipt → Report timestamps". `created_at` proxies receipt
     *                                     (no `received_at` column on water_samples).
     *   KPI-006 Data Entry Timeliness   — % of ANALYZED samples meeting (analyzed_at - created_at) ≤ 24h.
     *                                     SRS: "Entry timestamp vs analysis timestamp".
     */
    public function labKpis(DashboardRequest $request): JsonResponse
    {
        $tatTargetHours       = 48;  // KPI-005 — SRS
        $entryTargetHours     = 24;  // KPI-006 — industry default; SRS gives ≥98% compliance, not the threshold

        // Period bounds derived from the request's duration filter; used by the
        // calibration-log query (which doesn't go through applyDashboardFilters).
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($request);

        // RBAC: lab-incharge (and other scoped roles) see only their own
        // lab(s) in the KPI matrix — not the cross-lab comparison view
        // that SA / manager / view-only roles get.
        $userLabIds = $this->userLabIds();
        $labs = Laboratory::query()
            ->select('id', 'name')
            ->when($userLabIds !== null, fn(Builder $q) => $q->whereIn('id', $userLabIds))
            ->orderBy('name')
            ->get();

        // Manual KPI values (KPI-001/007/008/009) — admin enters monthly per lab
        // via the KPI Framework page. We pull the LATEST period per (lab, kpi)
        // and merge those into each lab's row below. Done in one query.
        $manualKpis = $this->latestManualKpiValues($labs->pluck('id')->all());

        $rows = $labs->map(function (Laboratory $lab) use ($request, $tatTargetHours, $entryTargetHours, $periodStart, $periodEnd, $manualKpis) {
            // Base sample query — scoped to this lab + honors dashboard filters
            // (region/district/duration/type). Lab filter from the request is
            // intentionally ignored since this endpoint produces a per-lab matrix.
            $baseSamples = WaterSample::query()
                ->where('laboratory_id', $lab->id)
                ->applyDashboardFilters($request, 'water_samples');

            // KPI-003: retest of unfit (after XEN action only)
            // SRS: "Retest module (after XEN action only)" — only count retests
            // that came from a XEN intervention, not lab-initiated retests.
            $totalUnfit = (clone $baseSamples)->whereIn('result', ['Unfit', '2'])->count();
            $retested   = (clone $baseSamples)
                ->whereIn('result', ['Unfit', '2'])
                ->where('current_round', '>', 1)
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                      ->from('water_sample_actions')
                      ->whereColumn('water_sample_actions.water_sample_id', 'water_samples.id');
                })
                ->count();

            // KPI-005: TAT — Receipt → Report (created_at → reported_at).
            // Only count samples that have actually been reported; samples
            // mid-pipeline don't yet have a TAT to measure.
            $tatPool   = (clone $baseSamples)->whereNotNull('reported_at')->count();
            $tatOnTime = (clone $baseSamples)->whereNotNull('reported_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, reported_at) <= ?', [$tatTargetHours])
                ->count();

            // KPI-006: data entry timeliness — Entry → Analysis (created_at → analyzed_at).
            $entryPool   = (clone $baseSamples)->whereNotNull('analyzed_at')->count();
            $entryOnTime = (clone $baseSamples)->whereNotNull('analyzed_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, created_at, analyzed_at) <= ?', [$entryTargetHours])
                ->count();

            // KPI-004: monthly sampling coverage.
            // SRS denominator: "15% of WSS count auto-calculated per district".
            // So target = 15% of total WSS in this lab's covered districts.
            // Numerator = distinct WSS sampled in the filtered period.
            $districtIds = $lab->coveredDistricts()->pluck('districts.id')->toArray();
            $wssInScope  = count($districtIds) > 0
                ? WaterScheme::whereIn('district_id', $districtIds)->count()
                : 0;
            $wssTotal    = (int) ceil($wssInScope * 0.15); // 15% monthly target
            $wssSampled  = (clone $baseSamples)
                ->whereNotNull('water_scheme_id')
                ->distinct()
                ->count('water_scheme_id');

            // KPI-002: equipment calibration — % schedules with ≥1 completed log in period
            $calSchedules = AssetMaintenanceSchedule::query()
                ->where('type', 'calibration')
                ->whereHas('laboratoryAsset', fn(Builder $q) => $q->where('laboratory_id', $lab->id))
                ->count();
            $calCompleted = AssetMaintenanceSchedule::query()
                ->where('type', 'calibration')
                ->whereHas('laboratoryAsset', fn(Builder $q) => $q->where('laboratory_id', $lab->id))
                ->whereHas('assetMaintenanceLogs', function (Builder $q) use ($periodStart, $periodEnd) {
                    $q->where('status', 'completed');
                    if ($periodStart) $q->where('event_date', '>=', $periodStart);
                    if ($periodEnd)   $q->where('event_date', '<=', $periodEnd);
                })
                ->count();

            // KPI-007: Staff Training Compliance — distinct staff with valid
            // training (valid_until in the future) / total staff at lab.
            // "Total staff at lab" = users attached via laboratory_user pivot.
            $totalStaff = DB::table('laboratory_user')->where('laboratory_id', $lab->id)->count();
            $trainedStaff = StaffTraining::query()
                ->where('laboratory_id', $lab->id)
                ->where('valid_until', '>=', now())
                ->whereNotNull('user_id')
                ->distinct('user_id')
                ->count('user_id');

            // KPI-009: Data Verification — sum(samples_matched) / sum(samples_verified)
            // across all visits for this lab in the filtered period. Visits where
            // samples_verified=0 are skipped (the sum naturally ignores them).
            $verAgg = VerificationVisit::query()
                ->where('laboratory_id', $lab->id)
                ->when($periodStart, fn($q) => $q->where('visit_date', '>=', $periodStart))
                ->when($periodEnd,   fn($q) => $q->where('visit_date', '<=', $periodEnd))
                ->selectRaw('COALESCE(SUM(samples_verified),0) AS verified, COALESCE(SUM(samples_matched),0) AS matched')
                ->first();
            $verVerified = (int) ($verAgg->verified ?? 0);
            $verMatched  = (int) ($verAgg->matched  ?? 0);

            // KPI-008: SOP Compliance — latest inspection per lab in the period.
            // pass / (pass + fail) × 100; N/A excluded from denominator.
            $latestInspection = AuditInspection::query()
                ->with('answers')
                ->where('laboratory_id', $lab->id)
                ->when($periodStart, fn($q) => $q->where('inspection_date', '>=', $periodStart))
                ->when($periodEnd,   fn($q) => $q->where('inspection_date', '<=', $periodEnd))
                ->orderByDesc('inspection_date')
                ->first();
            $auditPass = $latestInspection?->answers->where('answer', 'pass')->count() ?? 0;
            $auditFail = $latestInspection?->answers->where('answer', 'fail')->count() ?? 0;
            $auditScored = $auditPass + $auditFail;

            // KPI-001: PT — passed_results / total_submitted_results for this
            // lab across PT rounds whose round_date falls in the period.
            $ptAgg = PtRoundResult::query()
                ->join('pt_round_participants AS prp', 'prp.id', '=', 'pt_round_results.pt_round_participant_id')
                ->join('pt_rounds AS pr', 'pr.id', '=', 'prp.pt_round_id')
                ->where('prp.laboratory_id', $lab->id)
                ->when($periodStart, fn($q) => $q->where('pr.round_date', '>=', $periodStart))
                ->when($periodEnd,   fn($q) => $q->where('pr.round_date', '<=', $periodEnd))
                ->selectRaw('COUNT(*) AS submitted, SUM(CASE WHEN pt_round_results.passed = 1 THEN 1 ELSE 0 END) AS passed')
                ->first();
            $ptSubmitted = (int) ($ptAgg->submitted ?? 0);
            $ptPassed    = (int) ($ptAgg->passed    ?? 0);

            $pct = function (int $num, int $den): ?int {
                if ($den <= 0) return null;
                return (int) min(100, round(($num / $den) * 100));
            };

            // Manual KPI entries for this lab (latest period per kpi_code).
            // Used as a fallback when the module-driven KPI has no data yet
            // (e.g. KPI-007 has 0 staff or no training records → use admin's
            // monthly entry instead). Once any module produces a non-null value,
            // that takes precedence over the manual entry.
            $labManual = $manualKpis[$lab->id] ?? [];
            $manualValue = function (string $code) use ($labManual): ?int {
                $row = $labManual[$code] ?? null;
                if (!$row || (int) $row['denominator'] <= 0) return null;
                return (int) min(100, round(((int) $row['numerator'] / (int) $row['denominator']) * 100));
            };

            // KPI-007 prefers staff_trainings; falls back to manual when the
            // lab has no staff pivoted yet (totalStaff=0 → module can't compute).
            $kpi007 = $totalStaff > 0 ? $pct($trainedStaff, $totalStaff) : $manualValue('KPI-007');
            $kpi007Source = $totalStaff > 0 ? 'module' : (isset($labManual['KPI-007']) ? 'manual' : 'none');
            $kpi007Den    = $totalStaff > 0 ? $totalStaff : (isset($labManual['KPI-007']) ? (int) $labManual['KPI-007']['denominator'] : 0);

            // KPI-009 prefers verification_visits aggregation; falls back to
            // manual only when no visits have been logged for the period.
            $kpi009 = $verVerified > 0 ? $pct($verMatched, $verVerified) : $manualValue('KPI-009');
            $kpi009Source = $verVerified > 0 ? 'module' : (isset($labManual['KPI-009']) ? 'manual' : 'none');
            $kpi009Den    = $verVerified > 0 ? $verVerified : (isset($labManual['KPI-009']) ? (int) $labManual['KPI-009']['denominator'] : 0);

            // KPI-008 prefers latest audit inspection; falls back to manual
            // when no inspection has been recorded for this period.
            $kpi008 = $auditScored > 0 ? $pct($auditPass, $auditScored) : $manualValue('KPI-008');
            $kpi008Source = $auditScored > 0 ? 'module' : (isset($labManual['KPI-008']) ? 'manual' : 'none');
            $kpi008Den    = $auditScored > 0 ? $auditScored : (isset($labManual['KPI-008']) ? (int) $labManual['KPI-008']['denominator'] : 0);

            // KPI-001 prefers PT round results; falls back to manual when
            // this lab hasn't submitted any PT results in the period.
            $kpi001 = $ptSubmitted > 0 ? $pct($ptPassed, $ptSubmitted) : $manualValue('KPI-001');
            $kpi001Source = $ptSubmitted > 0 ? 'module' : (isset($labManual['KPI-001']) ? 'manual' : 'none');
            $kpi001Den    = $ptSubmitted > 0 ? $ptSubmitted : (isset($labManual['KPI-001']) ? (int) $labManual['KPI-001']['denominator'] : 0);

            return [
                'lab_id'   => $lab->id,
                'lab_name' => $lab->name,
                'kpis'     => [
                    'KPI-001' => $kpi001,
                    'KPI-002' => $pct($calCompleted, $calSchedules),
                    'KPI-003' => $pct($retested, $totalUnfit),
                    'KPI-004' => $pct($wssSampled, $wssTotal),
                    'KPI-005' => $pct($tatOnTime, $tatPool),
                    'KPI-006' => $pct($entryOnTime, $entryPool),
                    'KPI-007' => $kpi007,
                    'KPI-008' => $kpi008,
                    'KPI-009' => $kpi009,
                ],
                'denominators' => [
                    'KPI-001' => $kpi001Den,
                    'KPI-002' => $calSchedules,
                    'KPI-003' => $totalUnfit,
                    'KPI-004' => $wssTotal,
                    'KPI-005' => $tatPool,
                    'KPI-006' => $entryPool,
                    'KPI-007' => $kpi007Den,
                    'KPI-008' => $kpi008Den,
                    'KPI-009' => $kpi009Den,
                ],
                'sources' => [
                    'KPI-001' => $kpi001Source,
                    'KPI-002' => 'module',
                    'KPI-003' => 'module',
                    'KPI-004' => 'module',
                    'KPI-005' => 'module',
                    'KPI-006' => 'module',
                    'KPI-007' => $kpi007Source,
                    'KPI-008' => $kpi008Source,
                    'KPI-009' => $kpi009Source,
                ],
                'manual_periods' => [
                    'KPI-001' => $labManual['KPI-001']['period'] ?? null,
                    'KPI-007' => $labManual['KPI-007']['period'] ?? null,
                    'KPI-008' => $labManual['KPI-008']['period'] ?? null,
                    'KPI-009' => $labManual['KPI-009']['period'] ?? null,
                ],
            ];
        });

        // KPI catalog with display metadata. `missing_reason` lets the frontend
        // show a tooltip explaining why a column is "—" rather than a number.
        $catalog = [
            // rag_green / rag_amber lower bounds come straight from SRS §3.4.
            // Cell is GREEN if value >= rag_green, AMBER if value >= rag_amber,
            // else RED. Frontend uses these instead of a global 90/75 split.
            ['id' => 'KPI-001', 'name' => 'Inter-lab Comparison (PT)',     'category' => 'Quality',      'target_pct' => 95,  'rag_green' => 95,  'rag_amber' => 90, 'manual' => true,  'source_module' => 'pt_rounds',           'missing_reason' => 'No PT rounds recorded yet'],
            ['id' => 'KPI-002', 'name' => 'Equipment Calibration',         'category' => 'Quality',      'target_pct' => 100, 'rag_green' => 100, 'rag_amber' => 95, 'manual' => false, 'source_module' => 'asset_maintenance',   'missing_reason' => null],
            ['id' => 'KPI-003', 'name' => 'Retest of Unfit Samples',       'category' => 'Post-test',    'target_pct' => 85,  'rag_green' => 85,  'rag_amber' => 75, 'manual' => false, 'source_module' => 'water_sample_actions','missing_reason' => null],
            ['id' => 'KPI-004', 'name' => 'Monthly Sampling Coverage',     'category' => 'Sampling',     'target_pct' => 95,  'rag_green' => 95,  'rag_amber' => 85, 'manual' => false, 'source_module' => 'water_samples',       'missing_reason' => null],
            ['id' => 'KPI-005', 'name' => 'Turnaround Time (≤48h)',        'category' => 'Efficiency',   'target_pct' => 95,  'rag_green' => 95,  'rag_amber' => 85, 'manual' => false, 'source_module' => 'water_samples',       'missing_reason' => null],
            ['id' => 'KPI-006', 'name' => 'Data Entry Timeliness (≤24h)',  'category' => 'Data',         'target_pct' => 98,  'rag_green' => 98,  'rag_amber' => 90, 'manual' => false, 'source_module' => 'water_samples',       'missing_reason' => null],
            ['id' => 'KPI-007', 'name' => 'Staff Training Compliance',     'category' => 'HR',           'target_pct' => 100, 'rag_green' => 100, 'rag_amber' => 90, 'manual' => true,  'source_module' => 'staff_trainings',     'missing_reason' => 'No training records yet'],
            ['id' => 'KPI-008', 'name' => 'SOP Compliance',                'category' => 'Oversight',    'target_pct' => 100, 'rag_green' => 100, 'rag_amber' => 90, 'manual' => true,  'source_module' => 'audit_inspections',   'missing_reason' => 'No SOP audits recorded yet'],
            ['id' => 'KPI-009', 'name' => 'Data Verification',             'category' => 'Oversight',    'target_pct' => 100, 'rag_green' => 100, 'rag_amber' => 90, 'manual' => true,  'source_module' => 'verification_visits', 'missing_reason' => 'No verification visits recorded yet'],
        ];

        return response()->json([
            'data' => [
                'labs'  => $labs->map(fn($l) => ['id' => $l->id, 'name' => $l->name])->values(),
                'kpis'  => $catalog,
                'rows'  => $rows->values(),
                'meta'  => [
                    'tat_target_hours'   => $tatTargetHours,
                    'entry_target_hours' => $entryTargetHours,
                    'period_start'       => $periodStart?->toDateString(),
                    'period_end'         => $periodEnd?->toDateString(),
                ],
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Fetch the latest (most recent `period`) manual KPI entry per lab+kpi_code
     * for the given lab IDs. Returns a 2D map keyed by lab_id → kpi_code → row.
     *
     * Used by labKpis() to merge admin-entered values for KPI-001/007/008/009
     * (which have no operational data source) into the matrix.
     */
    private function latestManualKpiValues(array $labIds): array
    {
        if (empty($labIds)) return [];

        // ROW_NUMBER would be cleaner but MariaDB 10.2 baseline isn't guaranteed —
        // fall back to a "max period per group" subselect that works everywhere.
        $sub = DB::table('kpi_lab_periods')
            ->selectRaw('laboratory_id, kpi_code, MAX(period) AS max_period')
            ->whereIn('laboratory_id', $labIds)
            ->whereIn('kpi_code', ['KPI-001', 'KPI-007', 'KPI-008', 'KPI-009'])
            ->groupBy('laboratory_id', 'kpi_code');

        $rows = DB::table('kpi_lab_periods AS k')
            ->joinSub($sub, 'm', function ($join) {
                $join->on('k.laboratory_id', '=', 'm.laboratory_id')
                     ->on('k.kpi_code',      '=', 'm.kpi_code')
                     ->on('k.period',        '=', 'm.max_period');
            })
            ->select('k.laboratory_id', 'k.kpi_code', 'k.period', 'k.numerator', 'k.denominator')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[$r->laboratory_id][$r->kpi_code] = [
                'period'      => $r->period,
                'numerator'   => $r->numerator,
                'denominator' => $r->denominator,
            ];
        }
        return $out;
    }

    /**
     * Derive [start, end] Carbon bounds from the request's duration filter so
     * non-water_sample queries (e.g. asset_maintenance_logs) can reuse them.
     * Returns [null, null] when no duration is set ("all time").
     */
    private function resolvePeriodBounds(DashboardRequest $request): array
    {
        $duration = $request->input('duration');
        if (!$duration) return [null, null];

        if ($duration === \App\Enums\DurationEnum::MONTH->value) {
            if ($request->start_month && $request->end_month) {
                return [
                    \Carbon\Carbon::parse($request->start_month)->startOfDay(),
                    \Carbon\Carbon::parse($request->end_month)->endOfDay(),
                ];
            }
        }
        if ($duration === \App\Enums\DurationEnum::ANNUAL->value && $request->annual) {
            $year = (int) $request->annual;
            return [
                \Carbon\Carbon::create($year, 1, 1)->startOfYear(),
                \Carbon\Carbon::create($year, 12, 31)->endOfYear(),
            ];
        }
        if ($duration === \App\Enums\DurationEnum::QUARTER->value && $request->annual && $request->quarter) {
            $year = (int) $request->annual;
            $qMap = ['Q1' => [1,3], 'Q2' => [4,6], 'Q3' => [7,9], 'Q4' => [10,12]];
            if (isset($qMap[$request->quarter])) {
                [$sm, $em] = $qMap[$request->quarter];
                return [
                    \Carbon\Carbon::create($year, $sm, 1)->startOfMonth(),
                    \Carbon\Carbon::create($year, $em, 1)->endOfMonth(),
                ];
            }
        }
        return [null, null];
    }

    public function getWaterSchemeStatuCount(DashboardRequest $request): array
    {
        $query = WaterScheme::query();

        $totalSchemes = $query->whereHas('waterSample', fn(Builder $query) => $query->whereNotNull('result')->applyDashboardFilters($request, 'water_samples'))
            ->count();

        $fitWaterSchemesCount = (clone $query)
            ->whereHas('waterSample', fn(Builder $query) => $query->whereIn('result', ['Fit', '1'])
                ->applyDashboardFilters($request, 'water_samples')
            )
            ->count();

        $unFitWaterSchemesCount = (clone $query)
            ->whereHas('waterSample', fn(Builder $query) => $query->whereIn('result', ['Unfit', '2'])
                ->applyDashboardFilters($request, 'water_samples')
            )
            ->count();

        return [
            ['name'=> 'Safe', 'y' => round(($fitWaterSchemesCount / ($totalSchemes > 0 ? $totalSchemes : 1)) * 100)],
            ['name'=> 'Unsafe', 'y' => round(($unFitWaterSchemesCount / ($totalSchemes > 0 ? $totalSchemes : 1)) * 100)],
        ];
    }

    private function getWaterSamplesCount(DashboardRequest $request): array
    {
        $query = WaterSample::query()
            ->when(!$this->isSystemAdministrator, fn(Builder $query) => $query->where('district_id', '=', $this->authUser?->district_id))
            ->applyDashboardFilters($request, 'water_samples');
//            ->whereBetween('sampled_at', [now()->startOfMonth(), now()])
        $totalWaterSamples = (clone $query)->count();

        $totalWaterSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalWaterSamplesUnfit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        return [
            'total_water_samples' => $totalWaterSamples,
            'total_water_samples_fit' => $totalWaterSamplesFit,
            'total_water_samples_unfit' => $totalWaterSamplesUnfit,
        ];
    }

    private function getDistrictWiseWaterSampleResults(): array
    {
        $districtsWaterSamples = District::query()
            ->when(!$this->isSystemAdministrator, fn(Builder $query) => $query->where('id', '=', $this->authUser?->district_id))
            ->select('id', 'name')
            ->withCount([
                'waterSamples as total_water_sample',
                'waterSamples as total_unfit_water_sample' => fn($query) => $query->whereIn('result', ['Unfit', '2']),
                'waterSamples as total_fit_water_sample' => fn($query) => $query->whereIn('result', ['Fit', '1']),
            ])
            ->get();

        return $this->getWaterSampleGraphData($districtsWaterSamples);
    }

    private function getWaterSampleGraphData(Collection $collection): array
    {
        $labels = [];
        $dataSet = [];
        foreach ($collection as $key => $collect) {
            $labels[$key] = $collect->name;
            $dataSet['total_water_sample'][$key] = $collect->total_water_sample;
            $dataSet['total_fit_water_sample'][$key] = $collect->total_fit_water_sample;
            $dataSet['total_unfit_water_sample'][$key] = $collect->total_unfit_water_sample;
        }

        $dataSet = collect($dataSet)
            ->map(function ($data, $key) {
                $colors = ['#fbc02d', '#4caf50', '#ff4032'];
                return [
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'data' => $data,
                    'backgroundColor' => $colors[$this->index++]
                ];
            })
            ->values();

        $this->index = 0;

        return [
            'labels' => $labels,
            'datasets' => $dataSet,
        ];
    }

    private function getTotalComplaints(): array
    {
        $query = Complaint::query()
            ->when(!$this->isSystemAdministrator, function (Builder $query) {
                $query->whereHas('user', fn(Builder $query) => $query->where('district_id', '=', $this->authUser->district_id));
            });

        $totalComplaints = (clone $query)
            ->count();

        $totalPendingComplaints = (clone $query)
            ->where('status', '=', ComplaintStatusEnum::PENDING->value)
            ->count();

        $totalInProgressComplaints = (clone $query)
            ->where('status', '=', ComplaintStatusEnum::IN_PROGRESS->value)
            ->count();

        $totalClosedComplaints = (clone $query)
            ->where('status', '=', ComplaintStatusEnum::CLOSED->value)
            ->count();

        $totalReopenComplaints = (clone $query)
            ->where('status', '=', ComplaintStatusEnum::RE_OPENED->value)
            ->count();

        return [
            'labels' => array_merge(['Total Complaints'], array_map(fn($element) => ucwords(str_replace('_', ' ', $element)), ComplaintStatusEnum::values())),
            'datasets' => [
                [
                    'label' => 'Total Complaints',
                    'data' => [$totalComplaints, $totalPendingComplaints, $totalInProgressComplaints, $totalClosedComplaints, $totalReopenComplaints],
                    'backgroundColor' => '#AB47BC',
                ]
            ]
        ];
    }

    private function getTotalIssues(): array
    {
        $query = Issue::query()
            ->when(!$this->isSystemAdministrator, function (Builder $query) {
                $query->whereHas('user', fn(Builder $query) => $query->where('district_id', '=', $this->authUser->district_id));
            });

        $totalIssues = $query
            ->count();

        $totalPendingIssues = Issue::query()
            ->where('status', '=', IssueStatusEnum::PENDING->value)
            ->count();

        $totalInProgressIssues = Issue::query()
            ->where('status', '=', IssueStatusEnum::IN_PROGRESS->value)
            ->count();

        $totalClosedIssues = Issue::query()
            ->where('status', '=', IssueStatusEnum::CLOSED->value)
            ->count();

        $totalReopenIssues = Issue::query()
            ->where('status', '=', IssueStatusEnum::RE_OPENED->value)
            ->count();

        return [
            'labels' => array_merge(['Total Issues'], array_map(fn($element) => ucwords(str_replace('_', ' ', $element)), ComplaintStatusEnum::values())),
            'datasets' => [
                [
                    'label' => 'Total Issues',
                    'data' => [$totalIssues, $totalPendingIssues, $totalInProgressIssues, $totalClosedIssues, $totalReopenIssues],
                    'backgroundColor' => '#EC407A',
                ]
            ]
        ];
    }
}
