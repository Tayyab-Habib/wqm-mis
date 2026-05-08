<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\UserRoleEnum;
use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleTestStatusEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWaterSampleTestRequest;
use App\Models\Scopes\LatestScope;
use App\Models\Test;
use App\Models\User;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Models\WaterSamples\WaterSampleTest;
use App\Services\CalculateWaterQualityParameterService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class WaterSampleTestController extends Controller
{
    /**
     * Create Retest (Submit Sampling Data only)
     */
    public function retest(Request $request, WaterSample $waterSample): JsonResponse
    {
        // Ensure the water sample is UNFIT before allowing a retest
        if ($waterSample->current_status !== WaterSampleCurrentStatusEnum::UNFIT) {
            return response()->json([
                'message' => 'Only UNFIT samples can be retested.',
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $round = $waterSample->tests()->count();
            $authUserId = auth()->id();

            // Create new Retest (Sampling Data)
            $waterSampleTest = $waterSample->tests()->create([
                'round' => $round,
                'source_type' => $request->source_type,
                'source_sub_type' => $request->source_sub_type,
                'complaint' => $request->complaint,
                'complaint_by_other' => $request->complaint_by_other,
                'desired_test' => $request->desired_test ? implode(', ', $request->desired_test) : null,
                'sample_status' => $request->sample_status,
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

            // Re-create the water sample details (tests) with NT
            $desiredTestArray = is_array($request->desired_test) ? $request->desired_test : [];
            $testIds = Test::query()
                ->select('id')
                ->withoutGlobalScope(LatestScope::class)
                ->where('is_mandatory', '=', true)
                ->whereIn('type', $desiredTestArray)
                ->pluck('id');

            $onDemandTestIds = [];
            if ($request->has('on_demand_tests')) {
                $onDemandTestIds = Test::query()
                    ->whereIn('water_quality_parameter', $request->on_demand_tests)
                    ->pluck('id');
            }

            $mergedTestIds = $testIds->merge($onDemandTestIds)
                ->map(fn($testId) => [
                    'water_sample_id' => $waterSample->id, 
                    'water_sample_test_id' => $waterSampleTest->id, 
                    'test_id' => $testId, 
                    'input_result' => 'NT', 
                    'analysis_result' => 'NT'
                ])->toArray();

            WaterSampleDetail::query()->insert($mergedTestIds);

            // Update master table
            $waterSample->update([
                'current_round' => $round,
                'current_status' => WaterSampleCurrentStatusEnum::PENDING->value,
            ]);

            DB::commit();

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating retest round',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Success creating retest sampling round',
            'data' => $waterSample->load('tests.waterSampleDetails'),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Start Analysis Endpoint
     */
    public function startAnalysis(WaterSample $waterSample): JsonResponse
    {
        DB::beginTransaction();
        try {
            $activeTest = $waterSample->tests()->whereIn('status', [
                WaterSampleTestStatusEnum::PENDING->value,
                WaterSampleTestStatusEnum::IN_PROGRESS->value,
                WaterSampleTestStatusEnum::COMPLETED->value,
            ])->latest()->first();
            
            if (!$activeTest) {
                return response()->json([
                    'message' => 'No pending test found to analyze.',
                    'data' => null,
                ], SymfonyResponse::HTTP_BAD_REQUEST);
            }

            $activeTest->update(['status' => WaterSampleTestStatusEnum::IN_PROGRESS->value]);
            $waterSample->update(['current_status' => WaterSampleCurrentStatusEnum::IN_PROGRESS->value]);

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error starting analysis',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Analysis started successfully',
            'data' => null,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Submit Analysis Results
     */
    public function analyze(StoreWaterSampleTestRequest $request, WaterSample $waterSample): JsonResponse
    {
        $validatedData = $request->validated();
        $authUserId = auth()->id();
        $testResults = collect($validatedData['analysis_results']);

        // Accept PENDING, IN_PROGRESS, or COMPLETED (re-analysis scenario)
        $activeTest = $waterSample->tests()->whereIn('status', [
            WaterSampleTestStatusEnum::PENDING->value,
            WaterSampleTestStatusEnum::IN_PROGRESS->value,
            WaterSampleTestStatusEnum::COMPLETED->value,
        ])->latest()->first();

        if (!$activeTest) {
            return response()->json([
                'message' => 'No active test found to submit results for.',
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $testIds = $testResults->pluck('test_id');
        $waterParameters = Test::query()
            ->select(['id', 'unit', 'criteria', 'water_quality_parameter', 'who_guideline_start', 'who_guideline_end', 'laboratory_guideline_start', 'laboratory_guideline_end'])
            ->whereIn('id', $testIds)
            ->withoutGlobalScope(LatestScope::class)
            ->get()
            ->transform(function ($waterParameter) use ($testResults, $waterSample) {
                $testResult = $testResults->where('test_id', '=', $waterParameter->id)->first();
                return collect(array_merge($waterParameter->toArray(), ['input_result' => $testResult['analysis_result'], 'analysis_result' => $testResult['analysis_result'], 'water_sample_id' => $waterSample->id]));
            });

        $waterQualityParameterResults = (new CalculateWaterQualityParameterService($waterParameters, $activeTest->desired_test))
            ->calculateAnalysisResult();

        $waterParameterResult = [];
        $notTested = 0;
        foreach ($waterQualityParameterResults as $waterParameter) {
            if ($waterParameter['analysis_result'] === 'NT') {
                $notTested++;
            }
            if ($waterParameter['criteria']) {
                $waterParameterResult[] = ((float)$waterParameter['analysis_result'] > (float)$waterParameter['who_guideline_end']
                    || (float)$waterParameter['analysis_result'] < (float)$waterParameter['who_guideline_start']) && ($waterParameter['analysis_result'] !== 'NT')
                || (float)$waterParameter['analysis_result'] > (float)$waterParameter['laboratory_guideline_end'] || ($waterParameter['id'] === 20 && $waterParameter['analysis_result'] === '+ve')
                    ? 'Unfit'
                    : 'Fit';
            }
        }

        $finalResultString = $notTested !== $waterQualityParameterResults->count() && !$request->is_draft
            ? (in_array('Unfit', $waterParameterResult) ? 'Unfit' : 'Fit')
            : null;

        // force_unfit: QC failed and analyst chose Re-Analysis — mark as Unfit directly
        if ($request->boolean('force_unfit')) {
            $finalResultString = 'Unfit';
        }

        // force_fit: Override & Accept — mark as Fit regardless of QC
        if ($request->boolean('force_fit')) {
            $finalResultString = 'Fit';
        }
            
        $finalResultEnum = $finalResultString === 'Fit' ? WaterSampleTestResultEnum::FIT : ($finalResultString === 'Unfit' ? WaterSampleTestResultEnum::UNFIT : null);

        DB::beginTransaction();
        try {
            $activeTest->update([
                'analyzed_at' => now(),
                'status' => WaterSampleTestStatusEnum::COMPLETED->value,
                'result' => $finalResultEnum,
                'remarks' => $request->remarks,
                'research_officer_id' => $authUserId,
                'is_final' => true,
            ]);

            WaterSampleDetail::query()
                ->upsert($waterQualityParameterResults->map(function ($waterParameter) use ($authUserId, $activeTest) {
                    return [
                        'water_sample_id' => $waterParameter['water_sample_id'],
                        'water_sample_test_id' => $activeTest->id,
                        'test_id' => $waterParameter['id'],
                        'input_result' => $waterParameter['input_result'],
                        'analysis_result' => $waterParameter['analysis_result'],
                        'analyst_id' => $authUserId,
                    ];
                })->toArray(), ['water_sample_test_id', 'test_id'], ['analysis_result', 'input_result', 'analyst_id']);

            // Update master table
            $waterSample->update([
                'current_status' => $finalResultString === 'Fit' ? WaterSampleCurrentStatusEnum::FIT->value : ($finalResultString === 'Unfit' ? WaterSampleCurrentStatusEnum::UNFIT->value : WaterSampleCurrentStatusEnum::PENDING->value),
                'is_closed' => $finalResultString === 'Fit' || $activeTest->round >= 3,
                'result' => $finalResultEnum,
            ]);

            // SLA Logic: Trigger notification if UNFIT
            if ($finalResultString === 'Unfit') {
                try {
                    $xens = User::role(UserRoleEnum::xenTierRoles())
                        ->where('phed_division_id', $waterSample->phed_division_id)
                        ->get();
                    
                    foreach ($xens as $xen) {
                        DB::table('notifications')->insert([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'type' => 'App\Notifications\WaterSampleUnfit',
                            'notifiable_type' => User::class,
                            'notifiable_id' => $xen->id,
                            'data' => json_encode([
                                'message' => "Water Sample #{$waterSample->slug} is UNFIT. Corrective action required.",
                                'sample_id' => $waterSample->id,
                                'sample_slug' => $waterSample->slug,
                                'status' => 'UNFIT',
                                'round' => $activeTest->round,
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                } catch (\Exception $notifException) {
                    // Non-fatal — notification failure should not block result saving
                    info('Notification insert failed: ' . $notifException->getMessage());
                }
            }
            
            DB::commit();

            $message = $request->is_draft ? 'Success creating draft' : 'Success submitting analysis results';

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error submitting analysis',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => $message,
            'data' => $waterSample->load('tests.waterSampleDetails'),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Record WSS Fate Decision — lightweight endpoint, no full sample update required
     */
    public function recordFate(Request $request, WaterSample $waterSample): JsonResponse
    {
        $request->validate([
            'decision'          => ['required', 'string', 'in:monitor,advisory,decommission'],
            'authorised_by'     => ['nullable', 'string', 'max:255'],
            'decision_date'     => ['nullable', 'date'],
            'remarks'           => ['required', 'string', 'max:65535'],
            'doc_ref'           => ['nullable', 'string', 'max:255'],
        ]);

        try {
            // Store fate decision in remarks field and mark sample as closed
            $decisionLabel = match($request->decision) {
                'monitor'       => 'Continue Monitoring',
                'advisory'      => 'Issue Public Advisory',
                'decommission'  => 'Decommission / Abandon WSS',
                default         => $request->decision,
            };

            $fateNote = implode(' | ', array_filter([
                'FATE DECISION: ' . strtoupper($decisionLabel),
                $request->authorised_by ? 'Auth: ' . $request->authorised_by : null,
                $request->decision_date ? 'Date: ' . $request->decision_date : null,
                'Remarks: ' . $request->remarks,
                $request->doc_ref ? 'Ref: ' . $request->doc_ref : null,
            ]));

            $waterSample->update([
                'remarks'        => $fateNote,
                'is_closed'      => true,
                'current_status' => WaterSampleCurrentStatusEnum::CLOSED->value,
            ]);

            return response()->json([
                'message' => 'Fate decision recorded successfully',
                'data'    => ['decision' => $decisionLabel],
            ], SymfonyResponse::HTTP_OK);

        } catch (\Exception $e) {
            info('Fate decision error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error recording fate decision',
                'data'    => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
