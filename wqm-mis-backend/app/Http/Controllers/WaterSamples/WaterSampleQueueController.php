<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\WaterSampleResultEnum;
use App\Http\Controllers\Controller;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSampleQueueController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request, bool $isDraft = false): JsonResponse
    {
        $authUser = auth()->user();
        $query = WaterSample::query()
            ->select(['id',
                'test_type',
                'slug',
                'qr_code',
                'sampled_at',
                'water_scheme_id',
                'laboratory_id',
                'collectable_type',
                'sampling_point',
                'collected_by',
                'water_sample_address',
                'is_draft',
                'created_by',
                'modified_by',
                'temperature_in_celsius',
                'province_id',
                'division_id',
                'district_id',
                'region_id',
                'circle_id',
                'phed_division_id',
                'hub_lab_id',
                'result',
                'current_status',
                'current_round',
                'created_at'
            ])
            ->where(function ($q) use ($isDraft) {
                $q->where('is_draft', $isDraft)
                  ->orWhereNull('is_draft');
            })
            ->withCount('tests')
            ->with([
                'waterScheme:id,name',
                'province:id,name',
                'division:id,name',
                'district:id,name',
                'region:id,name',
                'circle:id,name',
                'phedDivision:id,name',
                'hubLab:id,name',
                'createdByUser:id,name',
                'waterSampleInvoice:id,water_sample_id,price,paid,balance'
            ]);

        if ($authUser->hasAnyRole(['laboratory-assistant', 'junior-clerk'])) {
            $query->where('created_by', '=', $authUser->id);
        }

        if (!$authUser->hasAnyRole(['system-administrator', 'laboratory-assistant'])) {
            $laboratoryId = $authUser->laboratoryUser->id;
            $query->where('laboratory_id', '=', $laboratoryId);
        }

        $waterSamples = $query->paginate(20);

        $query2 = WaterSample::query()->select('id');

        if ($authUser->hasAnyRole(['laboratory-assistant', 'junior-clerk'])) {
            $query2->where('created_by', '=', $authUser->id);
        }

        if (!$authUser->hasAnyRole(['system-administrator', 'laboratory-assistant'])) {
            $laboratoryId = $authUser->laboratoryUser->id;
            $query2->where('laboratory_id', '=', $laboratoryId);
        }


        $counts = [
            'total_water_samples' => (clone $query2)->count(),
            'fit_water_samples' => (clone $query2)->where('result', '=', WaterSampleResultEnum::FIT->value)->count(),
            'unfit_water_samples' => (clone $query2)->where('result', '=', WaterSampleResultEnum::UNFIT->value)->count(),
            'draft_water_samples' => (clone $query2)->where('is_draft', '=', true)->count(),
        ];

        if (0 === $waterSamples->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => [
                    'counts' => $counts,
                ],
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water samples',
            'data' => [
                'water_samples' =>$waterSamples,
                'counts' => $counts,
            ],
        ], SymfonyResponse::HTTP_OK);
    }
}
