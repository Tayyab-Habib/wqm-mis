<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\CollectableTypeEnum;
use App\Enums\CollectedInEnum;
use App\Enums\DesiredTestEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\TestTypeEnum;
use App\Enums\WaterSampleResultEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WaterSample\DeleteWaterSampleRequest;
use App\Http\Requests\WaterSample\ShowWaterSampleRequest;
use App\Http\Requests\WaterSample\StoreWaterSampleRequest;
use App\Http\Requests\WaterSample\UpdateWaterSampleRequest;
use App\Http\Requests\WaterSample\ViewWaterSampleRequest;
use App\Models\Client;
use App\Models\Scopes\LatestScope;
use App\Models\Test;
use App\Models\User;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Services\GenerateWaterSampleInvoice;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleTestStatusEnum;
use App\Models\WaterSamples\WaterSampleTest;

class WaterSampleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewWaterSampleRequest $request)
    {
        $authUser = auth()->user();
        $query = WaterSample::query()
            ->select(['id',
                'slug',
                'qr_code',
                'sampled_at',
                'water_scheme_id',
                'is_draft',
                'test_type',
                'created_by',
                'modified_by',
                'division_id',
                'district_id',
                'tehsil_id',
                'union_council_id',
                'region_id',
                'circle_id',
                'phed_division_id',
                'result',
                'current_status',
                'created_at',
            ])
            ->with([
                'waterScheme:id,name',
                'division:id,name',
                'district:id,name',
                'tehsil:id,name',
                'unionCouncil:id,name',
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
                'waterSamples' => $waterSamples,
                'counts' => $counts,
            ]
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreWaterSampleRequest $request
     * @return JsonResponse
     */
    public function store(StoreWaterSampleRequest $request)
    {
        $validatedData = $request->validated();
        try {
            DB::beginTransaction();
            $collectableType = User::class;
            $collectableId = auth()->id();
            $user = auth()->user();
            $laboratoryId = $user->laboratoryUser?->id;

            if (!$laboratoryId) {
                return response()->json([
                    'message' => 'Error creating water sample, add laboratory to user first',
                    'data' => null,
                ], SymfonyResponse::HTTP_FORBIDDEN);
            }

            if (CollectableTypeEnum::PRIVATE->value === $validatedData['collectable_type']) {

                $client = Client::query()
                    ->firstOrCreate([
                        'phone' => $validatedData['phone'],
                    ], [
                        'name' => $validatedData['name'],
                        'email' => $validatedData['email'] ?? null,
                        'address' => $validatedData['address'],
                        'type' => $validatedData['type'],
                        'organization_name' => $validatedData['organization_name'] ?? null
                    ]);

                $collectableType = Client::class;
                $collectableId = $client->id;
            }

            $waterSample = WaterSample::query()
                ->create(array_merge(
                    // Remove water_scheme_id for Private samples — it must be null
                    collect($validatedData)->when(
                        $validatedData['collectable_type'] !== CollectableTypeEnum::PHE->value,
                        fn($c) => $c->except(['water_scheme_id'])
                    )->toArray(),
                    [
                        'collectable_id' => $collectableId,
                        'collectable_type' => $collectableType,
                        'laboratory_id' => $laboratoryId,
                        'sampled_at' => $request->sampled_at,
                        'reported_at' => $request->reported_at,
                        'desired_test' => implode(', ', $request->desired_test),
                        'current_status' => WaterSampleCurrentStatusEnum::PENDING->value,
                        'current_round' => 0,
                        'is_closed' => false,
                    ]
                ));

            $waterSampleTest = WaterSampleTest::create([
                'water_sample_id' => $waterSample->id,
                'round' => 0,
                'source_type' => $request->source_type,
                'source_sub_type' => $request->source_sub_type,
                'complaint' => $request->complaint,
                'complaint_by_other' => $request->complaint_by_other,
                'desired_test' => $request->desired_test,
                'on_demand_tests' => $request->on_demand_tests,
                'sampling_point' => $request->sampling_point,
                'collected_by' => $request->collected_by,
                'collected_in' => $request->collected_in,
                'collected_in_other' => $request->collected_in_other,
                'temperature_in_celsius' => $request->temperature_in_celsius,
                'sampled_at' => $request->sampled_at,
                'reported_at' => $request->reported_at,
                'status' => WaterSampleTestStatusEnum::PENDING->value,
                'is_final' => false,
            ]);

            $desiredTest = collect($request->desired_test)
                ->map(fn($test) => $test === DesiredTestEnum::Chemical->value ? explode(' & ', $test) : $test)
                ->flatten(1)
                ->unique()->values()->all();

            $testIds = Test::query()
                ->select('id')
                ->withoutGlobalScope(LatestScope::class)
                ->where('is_mandatory', '=', true)
                ->whereIn('type', $desiredTest)
                ->pluck('id');

            $onDemandTestIds = [];
            if (array_key_exists('on_demand_tests', $validatedData)) {
                $onDemandTestIds = Test::query()
                    ->whereIn('water_quality_parameter', $validatedData['on_demand_tests'])
                    ->pluck('id');
            }

            $mergedTestIds = $testIds->merge($onDemandTestIds)
                ->map(fn($testId) => ['water_sample_id' => $waterSample->id, 'water_sample_test_id' => $waterSampleTest->id, 'test_id' => $testId, 'input_result' => 'NT', 'analysis_result' => 'NT'])
                ->toArray();

            WaterSampleDetail::query()
                ->insert($mergedTestIds);

            $invoice = (new GenerateWaterSampleInvoice())->execute($waterSample);

            DB::commit();

            return response()->json([
                'message' => 'Success creating water sample',
                'data' => $waterSample->load('waterSampleInvoice'),
            ], SymfonyResponse::HTTP_CREATED);
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating water sample',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param ShowWaterSampleRequest $request
     * @param WaterSample $waterSample
     * @return JsonResponse
     */
    public
    function show(ShowWaterSampleRequest $request, WaterSample $waterSample)
    {
        if ($this->restrictRelatedWaterSample($waterSample)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $waterSample->load([
            'waterScheme:id,name',
            'laboratory:id,name,logo',
            'province:id,name,logo',
            'division:id,name',
            'district:id,name',
            'tehsil:id,name',
            'unionCouncil:id,name',
            'labIncharge:id,name',
            'researchOfficer:id,name',
            'collectable',
            'waterSampleDetails.test',
            'createdByUser:id,name',
            'modifiedByUser:id,name',
            'region:id,name',
            'circle:id,name',
            'phedDivision:id,name',
            // Test rounds — needed by the Unfit Sample Trail "Trail" modal so it
            // can show R0/R1/R2/R3 history. Safe additive load: existing consumers
            // simply receive an extra `tests` array on the payload.
            'tests' => fn($q) => $q->orderBy('round')
                ->select(['id','water_sample_id','round','status','result','sampled_at','analyzed_at']),
        ]);

        return response()->json([
            'message' => 'Success fetching water sample',
            'data' => $waterSample,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWaterSampleRequest $request
     * @param WaterSample $waterSample
     * @return JsonResponse
     */
    public
    function update(UpdateWaterSampleRequest $request, WaterSample $waterSample)
    {
        if ($this->restrictRelatedWaterSample($waterSample)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        $validatedData = $request->validated();

        try {
            DB::beginTransaction();

            if ($validatedData['collected_in'] !== CollectedInEnum::OTHER->value) {

                $waterSample->update(['collected_in_other' => null]);
            }

            if ($validatedData['complaint'] !== ReasonForTestingEnum::OTHER->value) {

                $waterSample->update(['complaint_by_other' => null]);
            }

            if ($validatedData['source_type'] !== SourceTypeEnum::PUMPING->value) {

                $waterSample->update(['source_sub_type' => null]);
            }

            $waterSample->update($validatedData);

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error updating water sample',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }


        return response()->json([
            'message' => 'Success updating water sample',
            'data' => $waterSample,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteWaterSampleRequest $request
     * @param WaterSample $waterSample
     * @return JsonResponse
     */
    public
    function destroy(DeleteWaterSampleRequest $request, WaterSample $waterSample)
    {
        if ($this->restrictRelatedWaterSample($waterSample)) {
            return response()->json([
                'message' => 'Error user unauthorized',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $waterSample->delete();

        return response()->json([
            'message' => 'Success deleting water sample',
            'data' => null,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Checked whether water sample is related to concerned person/laboratory
     *
     * @param WaterSample $waterSample
     * @return bool
     */
    protected
    function restrictRelatedWaterSample(WaterSample $waterSample): bool
    {
        $authUser = auth()->user();

        if ($authUser->hasRole('system-administrator')) {
            return false;
        }

        if (!$authUser->hasAnyRole(['system-administrator', 'laboratory-assistant'])
            && (int)$waterSample->laboratory_id === (int)$authUser->laboratoryUser->id) {
            return false;
        }

        if ($authUser->hasAnyRole(['laboratory-assistant', 'junior_clerk'])
            && (int)$waterSample->created_by === $authUser->id) {
            return false;
        }

        return true;
    }
}
