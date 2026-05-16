<?php

namespace App\Http\Controllers\Laboratories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laboratory\DeleteLaboratoryRequest;
use App\Http\Requests\Laboratory\ShowLaboratoryRequest;
use App\Http\Requests\Laboratory\StoreLaboratoryRequest;
use App\Http\Requests\Laboratory\UpdateLaboratoryRequest;
use App\Http\Requests\Laboratory\ViewLaboratoryRequest;
use App\Http\Resources\LaboratoryResource;
use App\Models\Laboratories\Laboratory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewLaboratoryRequest $request)
    {
        $authUser = auth()->user();
        $laboratories = Laboratory::query()
            ->with([
                'focalPerson:id,name,designation_id' => [
                    'designation:id,name'
                ],
                'unionCouncil',
                'tehsil',
                'district',
                'division',
                'province',
            ])
            ->when(!$authUser->isUnscoped(), fn($query) => $query->where('district_id', '=', $authUser->district_id))
            ->get();

        if ($laboratories->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching laboratories',
            'data' => LaboratoryResource::collection($laboratories)
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreLaboratoryRequest $request
     * @return JsonResponse
     */
    public function store(StoreLaboratoryRequest $request)
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validated();

            $path = 'laboratories';
            if (!Storage::disk('public')->path($path)) {
                Storage::disk('public')->makeDirectory($path);
            }

            $logo = null;

            if ($request->hasFile('logo')) {
                $logo = Storage::disk('public')->putFile($path, $request->file('logo'));
            }

            $laboratory = Laboratory::query()
                ->create(array_merge($validatedData, ['logo' => $logo]));

            $laboratory->coveredDistricts()
                ->sync($request->covered_districts);

            $laboratory->users()->syncWithoutDetaching([
                $validatedData['focal_person_id'] => [
                    'present_duty' => $validatedData['present_duty'],
                    'assigned_parameters' => $validatedData['assigned_parameters'],
                ]]);
            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();

            return response()->json([
                'message' => 'Error creating laboratory',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Success creating laboratory',
            'data' => $laboratory,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowLaboratoryRequest $request
     * @param Laboratory $laboratory
     * @return JsonResponse
     */
    public function show(ShowLaboratoryRequest $request, Laboratory $laboratory)
    {
        if (auth()->user()->district_id !== $laboratory->district_id && !auth()->user()->isUnscoped()) {
            return response()->json([
                'message' => 'You do not have permission to view this laboratory',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Success fetching laboratory',
            'data' => $laboratory->load([
                'focalPerson:id,name,district_id' => [
                    'laboratoryDetails:id,user_id,assigned_parameters,present_duty'
                ],
                'unionCouncil',
                'tehsil',
                'district',
                'division',
                'province',
                'users:id',
                'coveredDistricts:id,name',
                'createdByUser:id,name',
                'modifiedByUser:id,name',
            ])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateLaboratoryRequest $request
     * @param Laboratory $laboratory
     * @return JsonResponse
     */
    public function update(UpdateLaboratoryRequest $request, Laboratory $laboratory)
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();
            $path = 'laboratories';
            if (!Storage::disk('public')->path($path)) {
                Storage::disk('public')->makeDirectory($path);
            }

            $logo = $laboratory->getAttributes()['logo'];

            if ($request->hasFile('logo')) {
                $logo = Storage::disk('public')->putFile($path, $request->file('logo'));
            }
            $laboratory->update(array_merge($validatedData, ['logo' => $logo]));

            $laboratory->users()
                ->syncWithoutDetaching([$validatedData['focal_person_id'] => [
                    'present_duty' => $validatedData['present_duty'],
                    'assigned_parameters' => $validatedData['assigned_parameters'],
                ]]);

            $laboratory->coveredDistricts()
                ->sync($request->covered_districts);

            DB::commit();
            return response()->json([
                'message' => 'Success updating laboratory',
                'data' => $laboratory->load([
                    'focalPerson:id,name,district_id' => [
                        'laboratoryDetails:id,user_id,assigned_parameters,present_duty'
                    ]])
            ]);
        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating laboratory',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteLaboratoryRequest $request
     * @param Laboratory $laboratory
     * @return JsonResponse
     */
    public function destroy(DeleteLaboratoryRequest $request, Laboratory $laboratory)
    {
//        if ($laboratory->loadExists('users')->users_exists) {
//            return response()->json([
//                'message' => 'Error deleting laboratory, delete all users belonging to this laboratory first',
//                'data' => null
//            ], SymfonyResponse::HTTP_BAD_REQUEST);
//        }

        $laboratory->delete();

        return response()->json([
            'message' => 'Success deleting laboratory',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
