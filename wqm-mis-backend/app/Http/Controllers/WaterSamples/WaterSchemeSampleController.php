<?php

namespace App\Http\Controllers\WaterSamples;

use App\Http\Controllers\Controller;
use App\Models\WaterSamples\WaterSample;
use App\Services\AuthScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaterSchemeSampleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        $query = WaterSample::query()
            ->select(['id', 'slug', 'water_scheme_id', 'laboratory_id', 'test_type', 'result', 'created_at', 'created_by', 'district_id', 'division_id', 'tehsil_id'])
            ->with([
                'waterScheme:id,name',
                'laboratory:id,name',
                'createdByUser:id,name',
                'division:id,name',
                'district:id,name',
                'tehsil:id,name',
            ])
            ->whereNotNull('water_scheme_id');

        if ($authUser->hasAnyRole(['laboratory-assistant', 'junior-clerk'])) {
            $query->where('created_by', '=', $authUser->id);
        }

        AuthScope::waterSamples($query, $authUser);

        $waterSamples = $query->paginate(20);

        return response()->json([
            'data' => $waterSamples,
            'message' => 'success fetching water schemes samples'
        ]);
    }
}
