<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchWaterSampleRequest;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchWaterSampleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchWaterSampleRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchWaterSampleRequest $request)
    {
        $query = WaterSample::query();
        $query->with([
            'waterScheme:id,name',
            'division:id,name',
            'district:id,name',
            'tehsil:id,name',
            'unionCouncil:id,name',
            'createdByUser:id,name',
            'waterSampleInvoice:id,water_sample_id,price,paid,balance'
        ]);

        $validatedData = $request->validated();
        if (isset($validatedData['test_type'])) {
            $query->where('test_type', '=', $validatedData['test_type']);
        }

        if (isset($validatedData['water_scheme_id'])) {
            $query->where('water_scheme_id', '=', $validatedData['water_scheme_id']);
        }

        if (isset($validatedData['source_type'])) {
            $query->where('source_type', '=', $validatedData['source_type']);
        }

        if (isset($validatedData['status'])) {
            $query->where('status', '=', $validatedData['status']);
        }

        if (isset($validatedData['laboratory_id'])) {
            $query->where('laboratory_id', '=', $validatedData['laboratory_id']);
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

        if (isset($validatedData['division_id'])) {
            $query->where('division_id', '=', $request->division_id);
        }

        if (isset($validatedData['province_id'])) {
            $query->where('province_id', '=', $validatedData['province_id']);
        }

        $waterSamples = $query->paginate(20);

        if (0 === $waterSamples->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $waterSamples,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving water samples',
            'data' => $waterSamples,
        ], SymfonyResponse::HTTP_OK);
    }
}
