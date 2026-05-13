<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintStatusEnum;
use App\Enums\InventoryStatusEnum;
use App\Enums\IssueStatusEnum;
use App\Enums\WaterSampleResultEnum;
use App\Http\Requests\DashboardRequest;
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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DashboardController extends Controller
{
    use DashboardFilterTrait;

    private int $index;
    private $authUser;


    private bool $isSystemAdministrator;

    public function __construct()
    {
        $this->index = 0;
        $this->middleware(function ($request, $next) {
            $this->authUser = auth()->user();
            $this->isSystemAdministrator = $this->authUser->hasRole('system-administrator');
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

        $totalPendingInventoryRequests = Inventory::query()
            ->when(!$this->isSystemAdministrator, fn(Builder $query) => $query->where('laboratory_id', '=', $this->authUser?->laboratoryUser->id))
            ->where('status', '=', InventoryStatusEnum::PENDING)
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

    public function getWaterSchemeStatuCount(DashboardRequest $request): array
    {
        $query = WaterScheme::query();

        $totalSchemes = $query->whereHas('waterSample', fn(Builder $query) => $query->whereNotNull('result')->applyDashboardFilters($request, 'water_samples'))
            ->count();

        $fitWaterSchemesCount = (clone $query)
            ->whereHas('waterSample', fn(Builder $query) => $query->where('result', '=', WaterSampleResultEnum::FIT->value)
                ->applyDashboardFilters($request, 'water_samples')
            )
            ->count();

        $unFitWaterSchemesCount = (clone $query)
            ->whereHas('waterSample', fn(Builder $query) => $query->where('result', '=', WaterSampleResultEnum::UNFIT->value)
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
            ->where('result', '=', WaterSampleResultEnum::FIT->value)
            ->count();

        $totalWaterSamplesUnfit = (clone $query)
            ->where('result', '=', WaterSampleResultEnum::UNFIT->value)
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
                'waterSamples as total_unfit_water_sample' => fn($query) => $query->where('result', '=', WaterSampleResultEnum::UNFIT->value),
                'waterSamples as total_fit_water_sample' => fn($query) => $query->where('result', '=', WaterSampleResultEnum::FIT->value),
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
