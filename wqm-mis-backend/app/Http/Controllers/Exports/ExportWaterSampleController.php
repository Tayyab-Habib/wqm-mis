<?php

namespace App\Http\Controllers\Exports;

use App\Exports\WaterSampleDataExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExportWaterSampleRequest;
use App\Models\District;
use App\Models\Division;
use App\Models\Laboratories\Laboratory;
use App\Models\Tehsil;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterScheme;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportWaterSampleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ExportWaterSampleRequest $request
     * @return BinaryFileResponse
     */
    public function __invoke(ExportWaterSampleRequest $request): BinaryFileResponse
    {
        $query = WaterSample::query()
            ->select([
                'id',
                'test_type',
                'slug',
                'water_scheme_id',
                'source_type',
                'sampling_point',
                'collected_by',
                'latitude',
                'longitude',
                'status',
                'temperature_in_celsius',
                'sampled_at',
                'analyzed_at',
                'collected_in',
                'collected_in_other',
                'complaint',
                'complaint_by_other',
                'desired_test',
                'created_by',
                'laboratory_id',
                'union_council_id',
                'tehsil_id',
                'district_id',
                'division_id',
                'province_id',
                'remarks',
                'result',
                'collectable_id',
                'collectable_type'
            ])
            ->with(['waterSampleDetails.test', 'waterSampleInvoice']);

            $fileName = '';
            $collectableTypeData = [
                'PHE' => 'App\Models\User',
                'Private' => 'App\Models\Client',
            ];

        $validatedData = $request->validated();

        if (isset($validatedData['water_scheme_id'])) {
            $fileName .= '-' . WaterScheme::query()->find($request->water_scheme_id)->name;
            $query->where('water_scheme_id', '=', $validatedData['water_scheme_id']);
        }

        if (isset($validatedData['laboratory_id'])) {
            $fileName .= '-' . Laboratory::query()->find($request->water_scheme_id)->name;
            $query->where('laboratory_id', '=', $validatedData['laboratory_id']);
        }

        if (isset($validatedData['sampling_point'])) {
            $fileName .= '-' . $request->sampling_point;
            $query->where('sampling_point', '=', $validatedData['sampling_point']);
        }

        if (isset($validatedData['collected_by'])) {
            $fileName .= '-' . $request->collected_by;
            $query->where('collected_by', '=', $validatedData['collected_by']);
        }

        if (isset($validatedData['complaint'])) {
            $fileName .= '-' . $request->complaint;
            $query->where('complaint', '=', $validatedData['complaint']);
        }

        if (isset($validatedData['result'])) {
            $fileName .= '-' . $request->result;
            $query->where('result', '=', $validatedData['result']);
        }

        if (isset($validatedData['collected_in'])) {
            $fileName .= '-' . $request->collected_by;
            $query->where('collected_in', '=', $validatedData['collected_in']);
        }

        if (isset($validatedData['source_type'])) {
            $fileName .= '-' . $request->source_type;
            $query->where('source_type', '=', $validatedData['source_type']);
        }

        if (isset($validatedData['collectable_type'])) {
                $fileName .= '-' . $request->collectable_type;
                $mappedCollectableType = $collectableTypeData[$validatedData['collectable_type']];
                $query->where('collectable_type', '=', $mappedCollectableType);
        }

        if (isset($validatedData['tehsil_id'])) {
            $fileName .= '-' . Tehsil::query()->find($request->tehsil_id)->name;
            $query->where('tehsil_id', '=', $validatedData['tehsil_id']);
        }

        if (isset($validatedData['district_id'])) {
            $fileName .= '-' . District::query()->find($request->district_id)->name;
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        if (isset($validatedData['division_id'])) {
            $fileName .= '-' . Division::query()->find($request->division_id)->name;
            $query->where('division_id', '=', $validatedData['division_id']);
        }

        if (isset($validatedData['starting_date'], $validatedData['ending_date'])) {
            $query->whereBetween('sampled_at', [$validatedData['starting_date'], $validatedData['ending_date']]);
        }

        if (isset($validatedData['desired_test'])) {
            $query->whereHas('waterSampleDetails.test', fn($query) => $query->whereIn('type', $request->desired_test));
        }

        $waterSamples = $query->get();

        return Excel::download(new WaterSampleDataExport($waterSamples), 'Water-Samples(' . $fileName . ').csv');
    }
}
