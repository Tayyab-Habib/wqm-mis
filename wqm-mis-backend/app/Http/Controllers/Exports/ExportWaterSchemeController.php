<?php

namespace App\Http\Controllers\Exports;

use App\Exports\WaterSchemeExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExportWaterSchemeRequest;
use App\Models\WaterScheme;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportWaterSchemeController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ExportWaterSchemeRequest $request
     * @return BinaryFileResponse
     */
    public function __invoke(ExportWaterSchemeRequest $request): BinaryFileResponse
    {
        $query = WaterScheme::query()->select([
            'name',
            'latitude',
            'longitude',
            'slug',
            'address',
            'created_by',
            'is_active',
            'source_type',
            'years_of_installation',
            'mode',
            'operation',
            'type_of_machine',
            'horse_power_motor',
            'storage',
            'capacity',
            'depth',
            'population',
            'chamber',
            'pipe_type',
            'remarks',
            'union_council_id',
            'tehsil_id',
            'district_id',
            'division_id',
            'province_id',
        ]);

        $validatedData = $request->validated();

        if (isset($validatedData['mode'])) {
            $query->where('mode', '=', $validatedData['mode']);
        }

        if (isset($validatedData['operation'])) {
            $query->where('operation', '=', $validatedData['operation']);
        }

        if (isset($validatedData['type_of_machine'])) {
            $query->where('type_of_machine', '=', $validatedData['type_of_machine']);
        }

        if (isset($validatedData['capacity'])) {
            $query->where('capacity', '=', $validatedData['capacity']);
        }

        if (isset($validatedData['population'])) {
            $query->where('population', '=', $validatedData['population']);
        }

        if (isset($validatedData['chamber'])) {
            $query->where('chamber', '=', $validatedData['chamber']);
        }

        if (isset($validatedData['source_type'])) {
            $query->where('source_type', '=', $validatedData['source_type']);
        }

        if (isset($validatedData['tehsil_id'])) {
            $query->where('tehsil_id', '=', $validatedData['tehsil_id']);
        }

        if (isset($validatedData['district_id'])) {
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        if (isset($validatedData['division_id'])) {
            $query->where('division_id', '=', $validatedData['division_id']);
        }

        $waterSchemes = $query->get();

        return Excel::download(new WaterSchemeExport($waterSchemes), 'water_scheme.csv');
    }
}
