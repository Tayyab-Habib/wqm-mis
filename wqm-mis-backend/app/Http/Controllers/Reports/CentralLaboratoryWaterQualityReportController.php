<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\InvokeCentralLaboratoryWaterQualityReportRequest;
use App\Models\District;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CentralLaboratoryWaterQualityReportController extends Controller
{
    private array $previousYearDateRanges = [
        'july' => 7,
        'august' => 8,
        'september' => 9,
        'october' => 10,
        'november' => 11,
        'december' => 12,
    ];

    private array $currentYearDateRanges = [
        'january' => 1,
        'february' => 2,
        'march' => 3,
        'april' => 4,
        'may' => 5,
        'june' => 6,
    ];

    /**
     * Handle the incoming request.
     *
     * @param InvokeCentralLaboratoryWaterQualityReportRequest $request
     * @return JsonResponse
     */
    public function __invoke(InvokeCentralLaboratoryWaterQualityReportRequest $request): JsonResponse
    {
        $startYear = $request->validated()['start_year'];
        $endYear = $request->validated()['end_year'];

        $dateRanges = $this->getDateRanges($startYear, $endYear);

        $waterSamples = District::query()
            ->select('id', 'name', 'division_id')
            ->orderBy('name')
            ->with('division:id,name')
            ->filterByDateRange('total', $dateRanges['total']['start'], $dateRanges['total']['end']);
        $months = array_merge($this->previousYearDateRanges, $this->currentYearDateRanges);

        foreach ($months as $monthName => $monthNumber) {
            $waterSamples->filterByDateRange($monthName, $dateRanges[$monthName]['start'], $dateRanges[$monthName]['end']);
        }

        return response()->json([
            'message' => 'Success fetching central-laboratory-water-quality-report',
            'data' => $waterSamples->get(),
        ], SymfonyResponse::HTTP_OK);
    }

    private function getDateRanges(string $startYear, string $endYear): array
    {
        $startYear = CarbonImmutable::createFromFormat('Y',$startYear);
        $endYear = CarbonImmutable::createFromFormat('Y',$endYear);


        $dateRanges = [
            'total' => [
                'start' => $startYear->month(7)->startOfMonth(),
                'end' => $endYear->month(6)->endOfMonth(),
            ],
        ];

        foreach ($this->previousYearDateRanges as $monthName => $monthNumber) {
            $dateRanges[$monthName] = [
                'start' => $startYear->month($monthNumber)->startOfMonth(),
                'end' => $startYear->month($monthNumber)->endOfMonth(),
            ];
        }

        foreach ($this->currentYearDateRanges as $monthName => $monthNumber) {
            $dateRanges[$monthName] = [
                'start' => $endYear->month($monthNumber)->startOfMonth(),
                'end' => $endYear->month($monthNumber)->endOfMonth(),
            ];
        }
        return $dateRanges;
    }
}
