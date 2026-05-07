<?php

namespace App\Http\Controllers\Search;

use App\Enums\WaterSampleResultEnum;
use App\Exports\WaterSampleExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchWaterSampleResultRequest;
use App\Models\District;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchWaterSampleResultController extends Controller
{
    private int $index;

    public function __construct()
    {
        $this->index = 0;      //for using different colors for each dataset
    }

    /**
     * Handle the incoming request.
     *
     * @param SearchWaterSampleResultRequest $request
     * @return JsonResponse
     */
    public function show(SearchWaterSampleResultRequest $request)
    {
        $waterSamples = $this->fetchWaterSamples($request);

        if ($waterSamples->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving water sample results',
            'data' => $waterSamples,
        ], SymfonyResponse::HTTP_OK);
    }

    public function export(SearchWaterSampleResultRequest $request)
    {
        $waterSamples = $this->fetchWaterSamples($request);

        return Excel::download(new WaterSampleExport($waterSamples), 'water_samples.csv');
    }

    public function generateGraph(SearchWaterSampleResultRequest $request)
    {
        $districtsWaterSampleResults = $this->getDistrictWiseWaterSampleResults($request);

        return response()->json([
            'data' => [
                'districts_water_sample_results' => $districtsWaterSampleResults,
            ]
        ], SymfonyResponse::HTTP_OK);
    }

    private function getDistrictWiseWaterSampleResults($data)
    {
        $districtsWaterSamples = District::query()
            ->whereHas('waterSamples')
            ->applyFilters($data)
            ->select('id', 'name')
            ->withCount([
                'waterSamples as total_water_sample',
                'waterSamples as total_unfit_water_sample' => function ($query) {
                    $query->where('result', '=', WaterSampleResultEnum::UNFIT->value);
                },
                'waterSamples as total_fit_water_sample' => function ($query) {
                    $query->where('result', '=', WaterSampleResultEnum::FIT->value);
                },
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

    private function fetchWaterSamples(SearchWaterSampleResultRequest $request)
    {
        $query = WaterSample::query()
            ->select(['id', 'slug','test_type','water_scheme_id','temperature_in_celsius', 'district_id', 'tehsil_id', 'latitude', 'longitude', 'result'])
            ->with(['district', 'tehsil', 'waterScheme']);

        $validatedData = $request->validated();

        if (isset($validatedData['water_scheme_id'])) {
            $query->where('water_scheme_id', '=', $validatedData['water_scheme_id']);
        }

        if (isset($validatedData['sampling_point'])) {
            $query->where('sampling_point', '=', $validatedData['sampling_point']);
        }

        if (isset($validatedData['collected_by'])) {
            $query->where('collected_by', '=', $validatedData['collected_by']);
        }

        if (isset($validatedData['source_type'])) {
            $query->where('source_type', '=', $validatedData['source_type']);
        }

        if (isset($validatedData['union_council_id'])) {
            $query->where('union_council_id', '=', $validatedData['union_council_id']);
        }

        if (isset($validatedData['tehsil_id'])) {
            $query->where('tehsil_id', '=', $validatedData['tehsil_id']);
        }

        if (isset($validatedData['district_id'])) {
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        if (isset($validatedData['starting_date'], $validatedData['ending_date'])) {
            $query->whereBetween('sampled_at', [$validatedData['starting_date'], $validatedData['ending_date']]);
        }

        return $query->get();
    }
}
