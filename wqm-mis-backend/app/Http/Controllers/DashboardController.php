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
use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
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


        $totalLaboratories = Laboratory::query()
            ->when(isset($request->division_id), fn(Builder $query) => $query->where('division_id', '=', $request->division_id))
            ->when(isset($request->district_id), fn(Builder $query) => $query->where('district_id', '=', $request->district_id))
            ->count();

        $totalWaterSchemes = WaterScheme::query()
            ->when(isset($request->division_id), fn(Builder $query) => $query->where('division_id', '=', $request->division_id))
            ->when(isset($request->district_id), fn(Builder $query) => $query->where('district_id', '=', $request->district_id))
            ->when(!$this->isSystemAdministrator, fn(Builder $query) => $query->where('district_id', '=', $this->authUser?->district_id))
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

        $operationWise = WaterScheme::query()
            ->select('operation')
            ->whereIn('operation', ['Operational', 'Non-Operational', 'Work in progress', 'Abandoned'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
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
            ->groupBy('chamber')
            ->get()
            ->mapWithKeys(function ($chamber) {
                return [$chamber->chamber => $chamber->count];
            })->toArray();

        $totalSourceCount = WaterScheme::query()
            ->whereIn('source_type', ['Gravity', 'Surface Water'])
            ->withoutGlobalScope(LatestScope::class)
            ->count();

        $source = WaterScheme::query()
            ->select('source_type')
            ->whereIn('source_type', ['Gravity', 'Surface Water'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->groupBy('source_type')
            ->get()
            ->mapWithKeys(function ($source) use ($totalSourceCount) {
                $percentage = ($source->count / $totalSourceCount) * 100;
                return [$source->source_type => round($percentage, 1)];
            });

        $sourceTypeChart = $dashboardService->getPieChart($source);

        $totalOperationalCount = WaterScheme::query()
            ->whereIn('operation', ['Operational', 'Non-Operational'])
            ->withoutGlobalScope(LatestScope::class)
            ->count();

        $operation = WaterScheme::query()
            ->select('operation')
            ->whereIn('operation', ['Operational', 'Non-Operational'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->groupBy('operation')
            ->get()
            ->mapWithKeys(function ($operation) use ($totalOperationalCount) {
                $percentage = ($operation->count / $totalOperationalCount) * 100;
                return [$operation->operation => round($percentage, 1)];
            });

        $operationalChart = $dashboardService->getPieChart($operation);

        $totalPowerInputCount = WaterScheme::query()
            ->whereIn('power_input', ['Wapda', 'Solar'])
            ->withoutGlobalScope(LatestScope::class)
            ->count();

        $powerInput = WaterScheme::query()
            ->select('power_input')
            ->whereIn('power_input', ['Wapda', 'Solar'])
            ->selectRaw('COUNT(*) as count')
            ->withoutGlobalScope(LatestScope::class)
            ->groupBy('power_input')
            ->get()
            ->mapWithKeys(function ($powerInput) use ($totalPowerInputCount) {
                $percentage = ($powerInput->count / $totalPowerInputCount) * 100;
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
     * (001/007/008/009) currently have no data source — returned as null so the
     * frontend can render them as "—" with a "data source not yet tracked"
     * tooltip. No fake values are returned.
     *
     * Definitions (per [[srs_kpi_definitions]]):
     *   KPI-002 Equipment Calibration   — % of calibration schedules with ≥1 completed log in the period
     *   KPI-003 Retest of Unfit Samples — % of unfit samples where current_round > 1
     *   KPI-004 Monthly Sampling Coverage — % of WSS in lab's covered districts that had ≥1 sample in the period
     *   KPI-005 Turnaround Time         — % of analyzed samples meeting analyzed_at - sampled_at ≤ 48h
     *   KPI-006 Data Entry Timeliness   — % of samples meeting created_at - sampled_at ≤ 24h
     */
    public function labKpis(DashboardRequest $request): JsonResponse
    {
        $tatTargetHours       = 48;  // KPI-005 — SRS
        $entryTargetHours     = 24;  // KPI-006 — industry default; SRS gives ≥98% compliance, not the threshold

        // Period bounds derived from the request's duration filter; used by the
        // calibration-log query (which doesn't go through applyDashboardFilters).
        [$periodStart, $periodEnd] = $this->resolvePeriodBounds($request);

        $labs = Laboratory::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $rows = $labs->map(function (Laboratory $lab) use ($request, $tatTargetHours, $entryTargetHours, $periodStart, $periodEnd) {
            // Base sample query — scoped to this lab + honors dashboard filters
            // (region/district/duration/type). Lab filter from the request is
            // intentionally ignored since this endpoint produces a per-lab matrix.
            $baseSamples = WaterSample::query()
                ->where('laboratory_id', $lab->id)
                ->applyDashboardFilters($request, 'water_samples');

            // KPI-003: retest of unfit
            $totalUnfit = (clone $baseSamples)->whereIn('result', ['Unfit', '2'])->count();
            $retested   = (clone $baseSamples)->whereIn('result', ['Unfit', '2'])->where('current_round', '>', 1)->count();

            // KPI-005: TAT (only count samples that have been both sampled and analyzed)
            $tatPool   = (clone $baseSamples)->whereNotNull('sampled_at')->whereNotNull('analyzed_at')->count();
            $tatOnTime = (clone $baseSamples)->whereNotNull('sampled_at')->whereNotNull('analyzed_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, sampled_at, analyzed_at) <= ?', [$tatTargetHours])
                ->count();

            // KPI-006: data entry timeliness
            $entryPool   = (clone $baseSamples)->whereNotNull('sampled_at')->count();
            $entryOnTime = (clone $baseSamples)->whereNotNull('sampled_at')
                ->whereRaw('TIMESTAMPDIFF(HOUR, sampled_at, created_at) <= ?', [$entryTargetHours])
                ->count();

            // KPI-004: monthly sampling coverage — schemes touched / schemes in covered districts
            $districtIds = $lab->coveredDistricts()->pluck('districts.id')->toArray();
            $wssTotal    = count($districtIds) > 0
                ? WaterScheme::whereIn('district_id', $districtIds)->count()
                : 0;
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

            $pct = function (int $num, int $den): ?int {
                if ($den <= 0) return null;
                return (int) min(100, round(($num / $den) * 100));
            };

            return [
                'lab_id'   => $lab->id,
                'lab_name' => $lab->name,
                'kpis'     => [
                    'KPI-001' => null,                      // No inter_lab_comparisons table
                    'KPI-002' => $pct($calCompleted, $calSchedules),
                    'KPI-003' => $pct($retested, $totalUnfit),
                    'KPI-004' => $pct($wssSampled, $wssTotal),
                    'KPI-005' => $pct($tatOnTime, $tatPool),
                    'KPI-006' => $pct($entryOnTime, $entryPool),
                    'KPI-007' => null,                      // No staff_trainings table
                    'KPI-008' => null,                      // No sop_audits table
                    'KPI-009' => null,                      // Verification definition unclear
                ],
                'denominators' => [
                    'KPI-002' => $calSchedules,
                    'KPI-003' => $totalUnfit,
                    'KPI-004' => $wssTotal,
                    'KPI-005' => $tatPool,
                    'KPI-006' => $entryPool,
                ],
            ];
        });

        // KPI catalog with display metadata. `missing_reason` lets the frontend
        // show a tooltip explaining why a column is "—" rather than a number.
        $catalog = [
            ['id' => 'KPI-001', 'name' => 'Inter-lab Comparison (PT)',  'target_pct' => 95,  'missing_reason' => 'No inter-lab comparison records tracked yet'],
            ['id' => 'KPI-002', 'name' => 'Equipment Calibration',      'target_pct' => 100, 'missing_reason' => null],
            ['id' => 'KPI-003', 'name' => 'Retest of Unfit Samples',    'target_pct' => 85,  'missing_reason' => null],
            ['id' => 'KPI-004', 'name' => 'Monthly Sampling Coverage',  'target_pct' => 95,  'missing_reason' => null],
            ['id' => 'KPI-005', 'name' => 'Turnaround Time (≤48h)',     'target_pct' => 95,  'missing_reason' => null],
            ['id' => 'KPI-006', 'name' => 'Data Entry Timeliness (≤24h)','target_pct' => 98, 'missing_reason' => null],
            ['id' => 'KPI-007', 'name' => 'Staff Training Compliance',  'target_pct' => 100, 'missing_reason' => 'No staff training records tracked yet'],
            ['id' => 'KPI-008', 'name' => 'SOP Compliance',             'target_pct' => 100, 'missing_reason' => 'No SOP audit data tracked yet'],
            ['id' => 'KPI-009', 'name' => 'Data Verification',          'target_pct' => 100, 'missing_reason' => 'Verification status definition pending'],
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
