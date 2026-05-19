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
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
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
                $query->orderBy('test_id', 'asc')->with(['test', 'analyst:id,name']);
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
                'analyst_name' => $waterSampleDetail->analyst?->name,
                // Preserve null so the frontend can show "NT" (Not Tested);
                // empty/0 here would shadow a missing result with a real-looking value.
                'input_result' => $waterSampleDetail->input_result,
                'analysis_result' => $waterSampleDetail->analysis_result,
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

        // Build the signed public viewer URL (no expiry — official lab reports
        // are referenced for years), then encode it as an inline SVG QR. The
        // signature is HMAC'd with APP_KEY so the slug space cannot be
        // enumerated by scanning sequential IDs.
        $publicShareUrl = URL::signedRoute('public-water-sample-report', [
            'water_sample' => $water_sample->id,
        ]);

        // ->generate() returns an Illuminate\Support\HtmlString. Laravel's
        // response()->json() serializes objects to {"html":"..."} instead of
        // invoking __toString(), so the SPA would receive `[object Object]`.
        $qrSvg = (string) QrCode::format('svg')
            ->size(140)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($publicShareUrl);

        return response()->json([
            'message' => 'Success fetching water-sample-report',
            'data' => [
                'water_sample' => $waterSample,
                'desired_tests' => implode(', ', $desiredTests),
                'abbreviations' => $abbreviations,
                'term_and_conditions' => $termAndConditions,
                'public_share_url' => $publicShareUrl,
                'qr_svg' => $qrSvg,
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

    /**
     * Public, signature-gated HTML viewer for the QR-code workflow. Renders
     * the same Blade as the PDF endpoint, but as a browser-friendly page so a
     * stakeholder scanning the QR on a printed report sees the result in their
     * phone browser without needing to log in. Access control is handled by
     * Laravel's `signed` middleware on the route.
     */
    public function publicShow(WaterSample $waterSample)
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

        // Same signed URL + QR that the SPA report receives, so the printed
        // public view also carries a re-scannable QR at the top-right.
        $publicShareUrl = URL::signedRoute('public-water-sample-report', [
            'water_sample' => $waterSample->id,
        ]);

        $qrSvg = (string) QrCode::format('svg')
            ->size(100)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($publicShareUrl);

        return view('waterSample.report', compact('waterSample', 'abbreviations', 'termAndConditions', 'desiredTests', 'qrSvg'));
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
