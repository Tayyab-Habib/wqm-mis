<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\CollectableTypeEnum;
use App\Enums\TestTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Abbreviation;
use App\Models\Test;
use App\Models\User;
use Illuminate\Support\Str;
use PDF;

use App\Models\Division;
use App\Models\Scopes\LatestScope;
use App\Models\TermAndCondition;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSampleReportController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param WaterSample $water_sample
     * @return JsonResponse
     */
    public function index(Request $request, WaterSample $water_sample): JsonResponse
    {
        $waterSample = $water_sample->load([
            'waterScheme:id,name',
            'province:id,name,logo',
            'division:id,name',
            'district:id,name',
            'tehsil:id,name',
            'unionCouncil:id,name',
            'region:id,name',
            'circle:id,name',
            'phedDivision:id,name',
            'hubLab:id,name',
            'collectable:id,name,phone,email',
            'labIncharge:id,name',
            'researchOfficer:id,name',
            'createdByUser:id,name',
            'modifiedByUser:id,name',
            'laboratory:id,name,email,phone,address,logo,fax',
            'waterSampleDetails' => function ($query) {
                $query->orderBy('test_id', 'asc')->with('test');
            },
            'tests'
        ])->loadCount('tests');

        $desiredTests = Test::query()
            ->select('water_quality_parameter')
            ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSample?->id)
                ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
            ->pluck('water_quality_parameter')->toArray();

        $waterSample->collectable_type = $waterSample->collectable_type === User::class
            ? CollectableTypeEnum::PHE->value
            : CollectableTypeEnum::PRIVATE->value;

        $waterSample->waterSampleDetails->transform(function ($waterSampleDetail) {
            return [
                'water_sample_id' => $waterSampleDetail->water_sample_id,
                'test_id' => $waterSampleDetail->test_id,
                'analyst_id' => $waterSampleDetail->analyst_id,
                'input_result' => $waterSampleDetail->input_result ?? 0,
                'analysis_result' => $waterSampleDetail->analysis_result ?? 0,
                'type' => $waterSampleDetail->test->type,
                'criteria' => $waterSampleDetail->test->criteria,
                'water_quality_parameter' => $waterSampleDetail->test->water_quality_parameter,
                'unit' => $waterSampleDetail->test->unit,
                'detectable_limit' => $waterSampleDetail->test->detectable_limit,
                'reference_method' => $waterSampleDetail->test->reference_method,
                'who_guideline_start' => $waterSampleDetail->test->who_guideline_start,
                'who_guideline_end' => $waterSampleDetail->test->who_guideline_end,
                'laboratory_guideline_start' => $waterSampleDetail->test->laboratory_guideline_start,
                'laboratory_guideline_end' => $waterSampleDetail->test->laboratory_guideline_end,
            ];
        });

        $waterSample = collect($waterSample)->replace(['water_sample_details' => $waterSample->waterSampleDetails->groupBy('type')]);

        $abbreviations = Abbreviation::query()
            ->select(['name', 'detail'])
            ->get();

        $termAndConditions = TermAndCondition::query()
            ->select('description')
            ->get();

        return response()->json([
            'message' => 'Success fetching water-sample-report',
            'data' => [
                'water_sample' => $waterSample,
                'desired_tests' => implode(', ', $desiredTests),
                'abbreviations' => $abbreviations,
                'term_and_conditions' => $termAndConditions,
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param int $year
     * @param Division $division
     * @param CollectableTypeEnum $collectableType
     * @param string $id
     * @return JsonResponse
     * @throws BindingResolutionException
     */
    public function show(Request $request, int $year, Division $division, CollectableTypeEnum $collectableType, string $id)
    {
        $waterSample = WaterSample::query()
            ->where('slug', '=', "{$year}/{$division->abbreviation}/{$collectableType->value}/{$id}")
            ->first();

        if (!$waterSample) {
            return response()->json([
                'message' => 'Error fetching water-sample-report',
                'data' => null,
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        $controller = app()->make(WaterSampleReportController::class);

        return $controller->callAction('index', ['request' => $request, 'water_sample' => $waterSample]);
    }

    public function generatePdf(WaterSample $waterSample)
    {
        $waterSample->load('waterSampleDetails.test', 'collectable');

        $abbreviations = Abbreviation::query()
            ->select(['name', 'detail'])
            ->get();

        $termAndConditions = TermAndCondition::query()
            ->select('description as term_condition')
            ->get()
            ->toArray();

        $desiredTests = Test::query()
            ->select('water_quality_parameter')
            ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSample?->id)
                ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
            ->pluck('water_quality_parameter')->toArray();

        $waterSample->collectable_type = $waterSample->collectable_type === User::class
            ? CollectableTypeEnum::PHE->value
            : CollectableTypeEnum::PRIVATE->value;

        $desiredTests = implode(',', $desiredTests);

        $pdf = PDF::loadView('waterSample.report', compact('waterSample', 'abbreviations', 'termAndConditions', 'desiredTests'));

        $pdf->setOption('page-size', 'A4');
        $fileName = 'water-sample-report-' . Str::replace('/', '-', $waterSample->slug) . '-' . now()->format('YmdTHis') . '.pdf';

        return $pdf->download($fileName);
    }
}
