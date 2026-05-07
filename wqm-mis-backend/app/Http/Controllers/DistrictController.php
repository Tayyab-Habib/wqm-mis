<?php

namespace App\Http\Controllers;

use App\Enums\WaterSampleResultEnum;
use App\Http\Requests\District\DeleteDistrictRequest;
use App\Http\Requests\District\ShowDistrictRequest;
use App\Http\Requests\District\StoreDistrictRequest;
use App\Http\Requests\District\UpdateDistrictRequest;
use App\Http\Requests\District\ViewDistrictRequest;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DistrictController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewDistrictRequest $request)
    {
        $districts = District::query()
            ->with([
                'division:id,name,province_id' => [
                    'province:id,name'
                ]
            ])
            ->get();

        if ($districts->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching districts',
            'data' => $districts
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreDistrictRequest $request
     * @return JsonResponse
     */
    public function store(StoreDistrictRequest $request)
    {
        $district = District::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating district',
            'data' => $district,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param District $district
     * @return JsonResponse
     */
    public function show(ShowDistrictRequest $request, District $district)
    {
        return response()->json([
            'message' => 'Success fetching district',
            'data' => $district
                ->load([
                    'waterSchemes:id,name,source_type,power_input,slug,district_id,tehsil_id,union_council_id' => [
                        'district:id,name',
                        'tehsil:id,name',
                        'unionCouncil:id,name',
                    ],
                    'division:id,name,province_id' => [
                        'province:id,name'
                    ],
                ])
                ->loadCount([
                    'laboratories as total_laboratories',
                    'waterSchemes as total_water_schemes',
                    'waterSamples as total_water_samples',
                    'waterSamples as total_fit_water_samples' => fn($query) => $query->where('result', '=', WaterSampleResultEnum::FIT->value),
                    'waterSamples as total_unfit_water_samples' => fn($query) => $query->where('result', '=', WaterSampleResultEnum::UNFIT->value),
                ])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDistrictRequest $request
     * @param District $district
     * @return JsonResponse
     */
    public function update(UpdateDistrictRequest $request, District $district)
    {
        $district->update($request->validated());

        if ($district->wasChanged()) {
            return response()->json([
                'message' => 'Success updating district',
                'data' => $district
            ]);
        }
        return response()->json([
            'message' => 'Error updating district'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param District $district
     * @return JsonResponse
     */
    public function destroy(DeleteDistrictRequest $request, District $district)
    {
        if ($district->loadExists('tehsils')->tehsils_exists) {
            return response()->json([
                'message' => 'Error deleting district, delete all tehsils belonging to this district first',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }
        $district->delete();

        return response()->json([
            'message' => 'Success deleting district',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
