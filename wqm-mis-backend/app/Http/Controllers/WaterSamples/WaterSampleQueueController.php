<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\WaterSampleResultEnum;
use App\Http\Controllers\Controller;
use App\Models\WaterSamples\WaterSample;
use App\Services\AuthScope;
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

        // RBAC: permission-driven scope so admin-created custom roles can
        // express either visibility model without a code change:
        //   view_all_lab_samples  → user sees every sample at their lab
        //   view_own_samples_only → user sees only samples they registered
        // Out-of-the-box: junior-clerk has own_only, lab-assistant has
        // all_lab (set up in RbacRolePermissionsSeeder). Unscoped admin roles
        // bypass entirely via AuthScope::waterSamples below.
        $applyRoleScope = function ($q) use ($authUser) {
            if ($authUser->isUnscoped()) return;
            if ($authUser->can('view_own_samples_only')) {
                $q->where('created_by', '=', $authUser->id);
            } elseif ($authUser->can('view_all_lab_samples')) {
                $userLabId = $authUser->laboratoryUser?->id;
                $q->where('laboratory_id', '=', $userLabId ?? 0);
            }
        };

        $applyRoleScope($query);
        AuthScope::waterSamples($query, $authUser);

        $waterSamples = $query->paginate(20);

        $query2 = WaterSample::query()->select('id');
        $applyRoleScope($query2);
        AuthScope::waterSamples($query2, $authUser);


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
