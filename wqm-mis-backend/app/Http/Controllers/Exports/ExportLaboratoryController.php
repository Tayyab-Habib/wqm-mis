<?php

namespace App\Http\Controllers\Exports;

use App\Exports\LaboratoryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExportLaboratoryRequest;
use App\Models\Laboratories\Laboratory;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportLaboratoryController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ExportLaboratoryRequest $request
     * @return BinaryFileResponse
     */
    public function __invoke(ExportLaboratoryRequest $request): BinaryFileResponse
    {
        $query = Laboratory::query()
            ->select([
                'id',
                'name',
                'latitude',
                'longitude',
                'phone',
                'fax',
                'email',
                'address',
                'created_by',
                'focal_person_id',
                'logo',
                'is_active',
                'union_council_id',
                'tehsil_id',
                'district_id',
                'division_id',
                'province_id',
            ]);

        $validatedData = $request->validated();

        if (isset($validatedData['tehsil_id'])) {
            $query->where('tehsil_id', '=', $validatedData['tehsil_id']);
        }

        if (isset($validatedData['division_id'])) {
            $query->where('division_id', '=', $validatedData['division_id']);
        }

        if (isset($validatedData['district_id'])) {
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        if (isset($validatedData['is_active'])) {
            $query->where('is_active', '=', $validatedData['is_active']);
        }
        $laboratories = $query->get();

        return Excel::download(new LaboratoryExport($laboratories), 'laboratory.csv');
    }
}
