<?php

namespace App\Http\Controllers\Search;

use App\Enums\WaterSampleResultEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchWaterSchemeRequest;
use App\Models\WaterScheme;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchWaterSchemeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchWaterSchemeRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchWaterSchemeRequest $request)
    {
        $validatedData = $request->validated();
        $query = WaterScheme::query()
            ->whereNot('name', '=', '-')
            ->whereNot('latitude', '=', '-')
            ->whereNot('longitude', '=', '-')
            ->select('id','name', 'address', 'latitude', 'longitude', 'district_id')
            ->withCount([
                'waterSamples as total_water_samples',
                'waterSamples as fit_water_samples' => fn(Builder $query) => $query->where('result', '=', WaterSampleResultEnum::FIT),
                'waterSamples as unfit_water_samples' => fn(Builder $query) => $query->where('result', '=', WaterSampleResultEnum::UNFIT),
            ]);


        if (isset($validatedData['union_council_id'])) {
            $query->where('union_council_id', '=', $validatedData['union_council_id']);
        }

        if (isset($validatedData['tehsil_id'])) {
            $query->where('tehsil_id', '=', $validatedData['tehsil_id']);
        }

        if (isset($validatedData['district_id'])) {
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        $waterSchemes = $query->get();

        if (0 === $waterSchemes->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $waterSchemes,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving water schemes',
            'data' => $waterSchemes,
        ], SymfonyResponse::HTTP_OK);
    }
}
