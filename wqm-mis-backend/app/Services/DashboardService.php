<?php

namespace App\Services;

use App\Enums\InventoryStatusEnum;
use App\Enums\OperationEnum;
use App\Enums\TestTypeEnum;
use App\Enums\WaterSampleResultEnum;
use App\Http\Requests\DashboardRequest;
use App\Models\Client;
use App\Models\District;
use App\Models\Laboratories\Laboratory;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Scopes\LatestScope;
use App\Models\Tehsil;
use App\Models\Test;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Models\WaterScheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;


class DashboardService
{
    protected DashboardRequest $request;

    public function __construct(DashboardRequest $request)
    {
        $this->request = $request;
    }

    public function getLaboratoryCoverage(): array
    {
        $query = Laboratory::query()
            ->when(isset($this->request->district_id), fn(Builder $query) => $query->where('district_id', '=', $this->request->district_id))
            ->applyDashboardFilters($this->request, 'laboratories')
            ->with('coveredDistricts');

        $totalLaboratories = (clone $query)->count();

        $totalDistricts = (clone $query)
            ->get()
            ->map(fn($lab) => $lab->coveredDistricts->map(fn($district) => $district->id))
            ->flatten()
            ->toArray();


        $coveredDistrictsQuery = (clone $query)
            ->withCount('coveredDistricts as covered_districts')
            ->get()
            ->sum('covered_districts');

        $totalCoveredTehsils = Tehsil::query()
            ->whereIn('district_id', $totalDistricts)
            ->count();

        return [
            'total_laboratories' => $totalLaboratories,
            'total_covered_districts' => $coveredDistrictsQuery,
            'total_covered_tehsils' => $totalCoveredTehsils,
        ];

    }

    public function getPercentageWaterSamplesCollectedFrom()
    {
//        $query = WaterSample::query()
//            ->applyDashboardFilters($this->request, 'water_samples', 'sampled_at');

        $query = WaterScheme::query()
            ->when(isset($this->request->laboratory_id), fn(Builder $query) => $query->where('id', '=', $this->request->laboratory_id))
            ->when(isset($this->request->division_id), fn(Builder $query) => $query->where('division_id', '=', $this->request->division_id));

        $totalWaterSchemes = (clone $query)->count();

        $operationalWaterSchemes = (clone $query)->where('operation', '=', OperationEnum::Operational->value)->count();
        $nonOperationalWaterSchemes = (clone $query)->where('operation', '=', OperationEnum::Non_Operational->value)->count();

        $percentageWaterSampleCollectedFromWss = $totalWaterSchemes !== 0 ? $operationalWaterSchemes / $totalWaterSchemes * 100 : 0;
        $percentageWaterSampleCollectedFromClient = $totalWaterSchemes !== 0 ? $nonOperationalWaterSchemes / $totalWaterSchemes * 100 : 0;

//        $totalWaterSampleCollected = (clone $query)->count();
//
//        $waterSampleCollectedFromWss = (clone $query)->whereNotNull('water_scheme_id')->count();
//        $waterSampleCollectedFromClient = (clone $query)->whereNull('water_scheme_id')->count();
//
//        $percentageWaterSampleCollectedFromWss = $totalWaterSampleCollected !== 0 ? $waterSampleCollectedFromWss / $totalWaterSampleCollected * 100 : 0;
//        $percentageWaterSampleCollectedFromClient = $totalWaterSampleCollected !== 0 ? $waterSampleCollectedFromClient / $totalWaterSampleCollected * 100 : 0;

        return [
            'total_water_schemes' => $totalWaterSchemes,
            'percentage_water_sample_collected_from_wss' => number_format($percentageWaterSampleCollectedFromWss, 1) . '%',
            'percentage_water_sample_collected_from_client' => number_format($percentageWaterSampleCollectedFromClient, 1) . '%',
        ];

    }

    public function getTestedWaterSamples(): array
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->when(isset($this->request->laboratory_id), function ($query) {
                return $query->where('laboratory_id', $this->request->laboratory_id);
            })
            ->when(isset($this->request->on_demand_tests), function ($query) {
                return $query->whereHas('waterSampleDetails.test', function ($query) {
                    $query->whereIn('water_quality_parameter', $this->request->on_demand_tests);
                });
            })
            ->applyDashboardFilters($this->request, 'water_samples')
            ->whereNotNull('result');

        $totalTestedWaterSamples = (clone $query)
            ->count();

        $totalWaterSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalWaterSamplesUnfit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        $percentageWaterSamplesFit = ($totalTestedWaterSamples > 0) ? ($totalWaterSamplesFit / $totalTestedWaterSamples) * 100 : 0;
        $percentageWaterSamplesUnFit = ($totalTestedWaterSamples > 0) ? ($totalWaterSamplesUnfit / $totalTestedWaterSamples) * 100 : 0;

        return ['total_tested_water_samples' => $totalTestedWaterSamples,
            'total_water_samples_fit' => number_format($percentageWaterSamplesFit, 1) . '%',
            'total_water_samples_unfit' => number_format($percentageWaterSamplesUnFit, 1) . '%',];
    }

    public function getPrivateWaterSamples(): array
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->when(isset($this->request->laboratory_id), function ($query) {
                return $query->where('laboratory_id', $this->request->laboratory_id);
            })
            ->when(isset($this->request->on_demand_tests), function ($query) {
                return $query->whereHas('waterSampleDetails.test', function ($query) {
                    $query->whereIn('water_quality_parameter', $this->request->on_demand_tests);
                });
            })
            ->whereNotNull('result')
            ->applyDashboardFilters($this->request, 'water_samples')
            ->where('collectable_type', '=', Client::class);

        $totalPrivateWaterSamples = (clone $query)
            ->count();

        $totalPrivateWaterSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalPrivateWaterSamplesUnfit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        $percentagePrivateWaterSamplesFit = ($totalPrivateWaterSamples > 0) ? ($totalPrivateWaterSamplesFit / $totalPrivateWaterSamples) * 100 : 0;
        $percentagePrivateWaterSamplesUnFit = ($totalPrivateWaterSamples > 0) ? ($totalPrivateWaterSamplesUnfit / $totalPrivateWaterSamples) * 100 : 0;

        return [
            'total_private_water_samples' => $totalPrivateWaterSamples,
            'total_private_water_samples_fit' => number_format($percentagePrivateWaterSamplesFit, 1) . '%',
            'total_private_water_samples_unfit' => number_format($percentagePrivateWaterSamplesUnFit, 1) . '%',
        ];
    }

    public function getOnDemandTestWaterSamples(): array
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->when(isset($this->request->laboratory_id), function ($query) {
                return $query->where('laboratory_id', $this->request->laboratory_id);
            })
            ->when(isset($this->request->on_demand_tests), function ($query) {
                return $query->whereHas('waterSampleDetails.test', function ($query) {
                    $query->whereIn('water_quality_parameter', $this->request->on_demand_tests);
                });
            })
            ->whereNotNull('result')
            ->applyDashboardFilters($this->request, 'water_samples')
            ->whereHas('waterSampleDetails.test', fn($query) => $query->where('type', '=', TestTypeEnum::ON_DEMAND));

        $totalOnDemandTestSamples = (clone $query)
            ->count();

        $totalOnDemandTestSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalOnDemandTestSamplesUnFit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        $percentageOnDemandWaterSamplesFit = ($totalOnDemandTestSamples > 0) ? ($totalOnDemandTestSamplesFit / $totalOnDemandTestSamples) * 100 : 0;
        $percentageOnDemandWaterSamplesUnFit = ($totalOnDemandTestSamples > 0) ? ($totalOnDemandTestSamplesUnFit / $totalOnDemandTestSamples) * 100 : 0;

        return [
            'total_on_demand_test_samples' => $totalOnDemandTestSamples,
            'total_on_demand_test_samples_fit' => number_format($percentageOnDemandWaterSamplesFit, 1) . '%',
            'total_on_demand_test_samples_unfit' => number_format($percentageOnDemandWaterSamplesUnFit, 1) . '%',
        ];
    }

    public function getPhysicalWaterSamples(): array
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->when(isset($this->request->laboratory_id), function ($query) {
                return $query->where('laboratory_id', $this->request->laboratory_id);
            })
            ->when(isset($this->request->on_demand_tests), function ($query) {
                return $query->whereHas('waterSampleDetails.test', function ($query) {
                    $query->whereIn('water_quality_parameter', $this->request->on_demand_tests);
                });
            })
            ->whereNotNull('result')
            ->applyDashboardFilters($this->request, 'water_samples')
            ->whereHas('waterSampleDetails.test', fn($query) => $query->where('type', '=', TestTypeEnum::PHYSICAL));

        $totalPhysicalSamples = (clone $query)
            ->count();

        $totalPhysicalSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalPhysicalSamplesUnFit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        $percentagePhysicalSamplesFit = ($totalPhysicalSamples > 0) ? ($totalPhysicalSamplesFit / $totalPhysicalSamples) * 100 : 0;
        $percentagePhysicalSamplesUnFit = ($totalPhysicalSamples > 0) ? ($totalPhysicalSamplesUnFit / $totalPhysicalSamples) * 100 : 0;

        return [
            'total_physical_samples' => $totalPhysicalSamples,
            'total_physical_samples_fit' => number_format($percentagePhysicalSamplesFit, 1) . '%',
            'total_physical_samples_unfit' => number_format($percentagePhysicalSamplesUnFit, 1) . '%',
        ];
    }

    public function getChemicalWaterSamples(): array
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->when(isset($this->request->laboratory_id), function ($query) {
                return $query->where('laboratory_id', $this->request->laboratory_id);
            })
            ->when(isset($this->request->on_demand_tests), function ($query) {
                return $query->whereHas('waterSampleDetails.test', function ($query) {
                    $query->whereIn('water_quality_parameter', $this->request->on_demand_tests);
                });
            })
            ->whereNotNull('result')
            ->applyDashboardFilters($this->request, 'water_samples')
            ->whereHas('waterSampleDetails.test', fn($query) => $query->where('type', '=', TestTypeEnum::CHEMICAL));

        $totalChemicalSamples = (clone $query)
            ->count();

        $totalChemicalSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalChemicalSamplesUnFit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        $percentageChemicalSamplesFit = ($totalChemicalSamples > 0) ? ($totalChemicalSamplesFit / $totalChemicalSamples) * 100 : 0;
        $percentageChemicalSamplesUnFit = ($totalChemicalSamples > 0) ? ($totalChemicalSamplesUnFit / $totalChemicalSamples) * 100 : 0;

        return [
            'total_chemical_samples' => $totalChemicalSamples,
            'total_chemical_samples_fit' => number_format($percentageChemicalSamplesFit, 1) . '%',
            'total_chemical_samples_unfit' => number_format($percentageChemicalSamplesUnFit, 1) . '%',
        ];
    }

    public function getMicroBialWaterSamples(): array
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->when(isset($this->request->laboratory_id), function ($query) {
                return $query->where('laboratory_id', $this->request->laboratory_id);
            })
            ->when(isset($this->request->on_demand_tests), function ($query) {
                return $query->whereHas('waterSampleDetails.test', function ($query) {
                    $query->whereIn('water_quality_parameter', $this->request->on_demand_tests);
                });
            })
            ->whereNotNull('result')
            ->applyDashboardFilters($this->request, 'water_samples')
            ->whereHas('waterSampleDetails.test', fn($query) => $query->whereIn('type', [TestTypeEnum::Microbiological_Kit->value, TestTypeEnum::Microbiological_Medical->value]));

        $totalMicroBialSamples = (clone $query)
            ->count();

        $totalMicroBialSamplesFit = (clone $query)
            ->whereIn('result', ['Fit', '1'])
            ->count();

        $totalMicroBialSamplesUnFit = (clone $query)
            ->whereIn('result', ['Unfit', '2'])
            ->count();

        $percentageMicroBialSamplesFit = ($totalMicroBialSamples > 0) ? ($totalMicroBialSamplesFit / $totalMicroBialSamples) * 100 : 0;
        $percentageMicroBialSamplesUnFit = ($totalMicroBialSamples > 0) ? ($totalMicroBialSamplesUnFit / $totalMicroBialSamples) * 100 : 0;

        return [
            'total_microbial_samples' => $totalMicroBialSamples,
            'total_microbial_samples_fit' => number_format($percentageMicroBialSamplesFit, 1) . '%',
            'total_microbial_samples_unfit' => number_format($percentageMicroBialSamplesUnFit, 1) . '%',
        ];
    }

    public function getWaterSchemesFitUnfit()
    {
        $totalWaterSchemes = WaterScheme::query()->count();

        $totalWaterSchemesFit = WaterScheme::query()
            ->whereHas('waterSample', fn(Builder $query) => $query->applyDashboardFilters($this->request, 'water_schemes')->whereIn('result', ['Fit', '1']))
            ->count();

        $totalWaterSchemesUnfit = WaterScheme::query()
            ->whereHas('waterSample', fn(Builder $query) => $query->applyDashboardFilters($this->request, 'water_schemes')->whereIn('result', ['Unfit', '2']))
            ->count();

        $percentageWaterSchemesFit = $totalWaterSchemes !== 0 ? $totalWaterSchemesFit / $totalWaterSchemes * 100 : 0;
        $percentageWaterSchemesUnfit = $totalWaterSchemes !== 0 ? $totalWaterSchemesUnfit / $totalWaterSchemes * 100 : 0;

        return [
            'percentage_water_schemes_fit' => number_format($percentageWaterSchemesFit, 1) . '%',
            'percentage_water_schemes_unfit' => number_format($percentageWaterSchemesUnfit, 1) . '%',
        ];
    }

    public function getPieChart(Collection $collection): array
    {
        $data = $collection->toArray();

        $chartData = [];

        foreach ($data as $sourceType => $count) {
            $chartData[] = [
                'name' => $sourceType,
                'y' => $count
            ];
        }
        // Generate the pie chart
        $chart = $chartData;

        return $chart;
    }

    public function getLaboratoryMaterialsAvailability(): array
    {
        $laboratoryMaterials = LaboratoryMaterial::query()
            ->when(!isset($this->request->laboratory_id), fn($query) => $query->where('laboratory_id', 1))
            ->when(isset($this->request->laboratory_id), fn($query) => $query->where('laboratory_id', $this->request->laboratory_id))
            ->withoutGlobalScope(LatestScope::class)
            ->select([
                'laboratory_materials.id',
                'materials.name as name',
                'laboratory_materials.laboratory_id',
                'laboratory_materials.available_quantity',
            ])
            ->selectRaw('laboratory_materials.quantity - laboratory_materials.available_quantity AS quantity, ( laboratory_materials.quantity * (materials.threshold / 100)) AS threshold')
            ->leftJoin('materials', 'laboratory_materials.material_id', '=', 'materials.id')
            ->get();

        $series = [
            [
                'name' => 'Total',
                'data' => $laboratoryMaterials->pluck('quantity')->map(fn($quantity) => (float)$quantity)->toArray(),
            ],
            [
                'name' => 'Available',
                'data' => $laboratoryMaterials->pluck('available_quantity')->map(fn($availableQuantity) => (float)$availableQuantity)->toArray(),
            ],
            [
                'type' => 'spline',
                'name' => 'threshold',
                'data' => $laboratoryMaterials->pluck('threshold')->map(fn($threshold) => (float)$threshold)->toArray(),
                'marker' => [
                    'lineWidth' => 2,
                    'fillColor' => 'white'
                ],
            ],
        ];

        $categories = $laboratoryMaterials->pluck('name')->toArray();

        return [
            'series' => $series,
            'categories' => $categories
        ];
    }

    public function getLaboratoryWiseTotalTestedWaterSamples(): array
    {
        $laboratoriesWaterSamples = Laboratory::query()
            ->select('id', 'name')
            ->withCount([
                'waterSamples as total' => fn($query) => $query->applyDashboardFilters($this->request, 'laboratories'),
                'waterSamples as tested' => fn($query) => $query->applyDashboardFilters($this->request, 'laboratories')->whereNotNull('result'),
            ])
            ->get();

        $series = [
            [
                'name' => 'Total',
                'data' => $laboratoriesWaterSamples->pluck('total')->toArray(),
            ],
            [
                'name' => 'Tested',
                'data' => $laboratoriesWaterSamples->pluck('tested')->toArray(),
            ],
        ];

        $categories = $laboratoriesWaterSamples->pluck('name')->toArray();

        return [
            'series' => $series,
            'categories' => $categories
        ];
    }

    public function getAssociatedWaterSchemes(): array
    {
        $districts = District::query()
            ->select(['id', 'name'])
            ->whereHas('laboratories')
            ->withCount('waterSchemes as total')
            ->get()
            ->mapWithKeys(function ($district) {
                return [$district->name => $district->total];
            })
            ->toArray();

        return [
            'categories' => array_keys($districts),
            'series' => [[
                'name' => 'Associated Water Schemes',
                'data' => array_values($districts)
            ]],
        ];
    }

    public function getLaboratoriesWaterSampleResults(): array
    {
        $laboratoriesWaterSamples = Laboratory::query()
            ->select('id', 'name')
            ->withCount([
                'waterSamples as tested' => fn($query) => $query->applyDashboardFilters($this->request, 'water_samples')->whereNotNull('result'),
                'waterSamples as unfit' => fn($query) => $query->applyDashboardFilters($this->request, 'water_samples')->whereIn('result', ['Unfit', '2']),
                'waterSamples as fit' => fn($query) => $query->applyDashboardFilters($this->request, 'water_samples')->whereIn('result', ['Fit', '1']),
            ])
            ->get();

        $series = [
            [
                'name' => 'Tested',
                'data' => $laboratoriesWaterSamples->pluck('tested')->toArray(),
            ],
            [
                'name' => 'Fit',
                'data' => $laboratoriesWaterSamples->pluck('fit')->toArray(),
            ],
            [
                'name' => 'Unfit',
                'data' => $laboratoriesWaterSamples->pluck('unfit')->toArray(),
            ],
        ];

        $categories = $laboratoriesWaterSamples->pluck('name')->toArray();

        return [
            'series' => $series,
            'categories' => $categories
        ];
    }

    public function getLaboratoryWiseInventoryRequests(): array
    {
        $laboratories = Laboratory::query()
            ->select(['id', 'name'])
            ->withCount([
                'inventories as pending' => fn($query) => $query->where('status', '=', InventoryStatusEnum::PENDING->value),
                'inventories as approved' => fn($query) => $query->where('status', '=', InventoryStatusEnum::APPROVED->value),
            ])
            ->get();

        $series = [
            [
                'name' => 'pending',
                'data' => $laboratories->pluck('pending')->toArray(),
            ],
            [
                'name' => 'approved',
                'data' => $laboratories->pluck('approved')->toArray(),
            ],
        ];

        $categories = $laboratories->pluck('name')->toArray();

        return [
            'series' => $series,
            'categories' => $categories
        ];
    }

    public function getLaboratoryWiseRevenue(): array
    {
        $laboratories = Laboratory::query()->select('name')->pluck('name')->toArray();
        $laboratories = array_fill_keys($laboratories, 0);

        $laboratoryRevenue = collect($laboratories)->merge(Laboratory::query()
            ->select(['laboratories.id', 'laboratories.name'])
            ->selectRaw('SUM(payments.total) as total_payment')
            ->applyDashboardFilters($this->request, 'laboratories', 'payments.created_at')
            ->leftJoin('payments', 'laboratories.id', '=', 'payments.laboratory_id')
            ->groupBy('payments.laboratory_id')
            ->get()
            ->mapWithKeys(function ($revenue) {
                return [
                    $revenue->name => (float)$revenue->total_payment ?? 0
                ];
            }))
            ->toArray();

        return [
            'categories' => array_keys($laboratoryRevenue),
            'series' => [
                [
                    'name' => 'Laboratories wise Revenue',
                    'data' => array_values($laboratoryRevenue),
                ],
            ]
        ];
    }

    public function getDistrictWiseContaminantsCount(): array
    {
        $waterParameters = Test::query()
            ->select('water_quality_parameter', 'unit')
            ->when(isset($this->request->on_demand_test), fn($query) => $query->whereIn('water_quality_parameter', $this->request->on_demand_test))
            ->where('type', TestTypeEnum::ON_DEMAND->value)
            ->orderBy('water_quality_parameter')
            ->get()
            ->pluck('water_quality_parameter')
            ->toArray();

        $waterParameters = array_fill_keys($waterParameters, 0);

        $contaminantsWiseWaterParameters = WaterSampleDetail::query()
            ->withoutGlobalScope(LatestScope::class)
            ->select(['tests.id as id', 'districts.name as district_name', 'tests.water_quality_parameter as water_parameter'])
            ->selectRaw('COUNT(water_sample_details.water_sample_id) as count_water_parameters')
            ->applyDashboardFilters($this->request, 'water_samples', 'water_samples.created_at')
            ->when(isset($this->request->district_id), fn($query) => $query->where('water_samples.district_id', $this->request->district_id))
            ->when(isset($this->request->division_id), fn($query) => $query->where('water_samples.division_id', $this->request->division_id))
            ->when(isset($this->request->laboratory_id), fn($query) => $query->where('water_samples.laboratory_id', $this->request->laboratory_id))
            ->leftJoin('water_samples', 'water_sample_details.water_sample_id', '=', 'water_samples.id')
            ->join('districts', 'water_samples.district_id', '=', 'districts.id')
            ->leftJoin('tests', 'water_sample_details.test_id', '=', 'tests.id')
//            ->whereNotNull('result')
            ->when(isset($this->request->on_demand_test), fn($query) => $query->whereIn('water_quality_parameter', $this->request->on_demand_test))
            //            ->where(function ($query) {
//                $query->whereColumn('water_sample_details.analysis_result', '>', 'tests.who_guideline_end')
//                    ->orWhereColumn('water_sample_details.analysis_result', '>', 'tests.laboratory_guideline_end');
//            })
            ->groupBy('water_sample_details.test_id', 'water_samples.district_id')
            ->orderBy('districts.name')
            ->get()
            ->groupBy('district_name')
            ->map(function ($district) use ($waterParameters) {
                $districtWaterParameters = $district->sortBy('water_parameter')
                    ->mapWithKeys(function ($parameter) {
                        return [$parameter->water_parameter => $parameter->count_water_parameters];
                    });
                return collect($waterParameters)->merge($districtWaterParameters);
            });


        $labels = [];
        $dataSets = [];
        foreach ($contaminantsWiseWaterParameters as $key => $waterParameters) {
            $labels[] = $key;
            foreach (array_keys($waterParameters->toArray()) as $waterParameter) {
                $dataSets[$waterParameter][$key] = $waterParameters[$waterParameter];
            }
        }
        $dataSets = collect($dataSets)
            ->map(function ($data, $key) {
                return [
                    'name' => $key,
                    'data' => array_values($data),
                ];
            })->values();

        return [
            'categories' => $labels,
            'series' => $dataSets,
        ];
    }

    public function getMonthlyWaterSchemesTestingCount()
    {
        $months = $this->getMonths();

        $waterSamples = WaterSample::query()
            ->withoutGlobalScope(LatestScope::class)
            ->applyDashboardFilters($this->request, 'water_samples')
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") AS month, COUNT(DISTINCT water_scheme_id) as total_count')
            ->distinct()
            ->whereNotNull('water_scheme_id')
            ->groupBy('month')
            ->get()
            ->map(function ($waterSample) {
                return [Carbon::parse($waterSample->month)->format('F') => $waterSample->total_count];
            })
            ->mapWithKeys(fn($waterSample) => $waterSample);

        $waterSamples = collect($months)->merge($waterSamples)->toArray();

        return [
            'categories' => array_keys($waterSamples),
            'series' => [
                [
                    'name' => 'Total Tested Water Schemes',
                    'data' => array_values($waterSamples),
                ],
            ]
        ];
    }

    public function getMonthlyWaterSampleFitUnfit()
    {
        $months = $this->getMonths();

        $waterSamples = WaterSample::query()
            ->withoutGlobalScope(LatestScope::class)
            ->applyDashboardFilters($this->request, 'water_samples')
            ->selectRaw(
                'DATE_FORMAT(created_at, "%Y-%m") AS month, CASE
                        WHEN result IN ("Fit", "1") THEN "fit"
                        WHEN result IN ("Unfit", "2") THEN "unfit"
                    END AS result, COUNT(result) as total_count')
            ->whereNotNull('result')
            ->groupBy('month', 'result')
            ->get()
            ->groupBy('result')
            ->map(function ($result) use ($months) {
                $results = collect($months)
                    ->merge(collect($result)
                        ->map(function ($month) {
                            return [Carbon::parse($month->month)->format('F') => $month->total_count];
                        })
                        ->mapWithKeys(fn($element) => $element)
                    )->toArray();
                return [
                    'labels' => array_keys($results),
                    'data' => array_values($results),
                ];
            });
        $categories = [];
        $series = [];
        foreach ($waterSamples as $key => $waterSample) {
            $categories = $waterSample['labels'];
            $series[] = [
                'name' => $key,
                'data' => $waterSample['data']
            ];
        }

        return [
            'categories' => $categories,
            'series' => $series
        ];
    }

    public function getMonths()
    {
        $months = [
            'January',
            'February',
            'March',
            'April',
            'May',
            'June',
            'July',
            'August',
            'September',
            'October',
            'November',
            'December',
        ];

        return array_fill_keys($months, 0);
    }
}
