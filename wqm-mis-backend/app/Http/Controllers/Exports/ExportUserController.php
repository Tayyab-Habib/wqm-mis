<?php

namespace App\Http\Controllers\Exports;

use App\Exports\UserExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExportUserRequest;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportUserController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ExportUserRequest $request
     * @return BinaryFileResponse
     */
    public function __invoke(ExportUserRequest $request): BinaryFileResponse
    {
        $query = User::query()
            ->select([
                'id',
                'name',
                'email',
                'phone',
                'image',
                'gender',
                'date_of_birth',
                'date_of_joining',
                'is_active',
                'employee_status',
                'created_by',
                'career_background',
                'educational_background',
                'basic_pay_scale',
                'designation_id',
                'district_id'
            ])
            ->with('laboratories');

        $validatedData = $request->validated();

        if (isset($validatedData['laboratory_id'])) {
            $query->whereHas('laboratories', function ($query) use ($validatedData) {
                $query->where('laboratories.id', $validatedData['laboratory_id']);
            });
        }

        if (isset($validatedData['district_id'])) {
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        if (isset($validatedData['designation_id'])) {
            $query->where('designation_id', '=', $validatedData['designation_id']);
        }

        if (isset($validatedData['division_id'])) {
            $query->whereHas('district.division', function ($query) use ($validatedData) {
                $query->where('divisions.id', $validatedData['division_id']); 
            });
        }

        if (isset($validatedData['is_active'])) {
            $query->where('is_active', '=', $validatedData['is_active']);
        }
        $users = $query->get();

        return Excel::download(new UserExport($users), 'user.csv');
    }
}
