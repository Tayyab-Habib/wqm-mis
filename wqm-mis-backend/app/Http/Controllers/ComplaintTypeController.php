<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaint\DeleteComplaintTypeRequest;
use App\Http\Requests\Complaint\StoreComplaintTypeRequest;
use App\Http\Requests\Complaint\ViewComplaintTypeRequest;
use App\Models\ComplaintType;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ComplaintTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewComplaintTypeRequest $request)
    {
        $complaintTypes = ComplaintType::query()->get();

        if ($complaintTypes->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching complaint-types',
            'data' => $complaintTypes
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreComplaintTypeRequest $request
     * @return JsonResponse
     */
    public function store(StoreComplaintTypeRequest $request)
    {
        $validatedData = $request->validated();

        $complaintType = ComplaintType::query()
            ->create($validatedData);

        return response()->json([
            'message' => 'Success creating complaint-type',
            'data' => $complaintType
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteComplaintTypeRequest $request
     * @param ComplaintType $complaintType
     * @return JsonResponse
     */
    public function destroy(DeleteComplaintTypeRequest $request, ComplaintType $complaintType)
    {
        if ($complaintType->loadExists('complaints')->complaints_exists) {
            return response()->json([
                'message' => 'Error deleting complaint-type, delete all complaints belonging to this complaint-type first',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $complaintType->delete();

        return response()->json([
            'message' => 'Success deleting complaint-type',
            'data' => $complaintType
        ], SymfonyResponse::HTTP_OK);
    }
}
