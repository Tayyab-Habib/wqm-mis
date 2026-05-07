<?php

namespace App\Http\Controllers;

use App\Http\Requests\Designation\DeleteDesignationRequest;
use App\Http\Requests\Designation\ShowDesignationRequest;
use App\Http\Requests\Designation\StoreDesignationRequest;
use App\Http\Requests\Designation\UpdateDesignationRequest;
use App\Http\Requests\Designation\ViewDesignationRequest;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DesignationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewDesignationRequest $request): JsonResponse
    {
        $designations = Designation::query()
            ->get();

        if (0 === $designations->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => ''
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieved designations',
            'data' => $designations
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreDesignationRequest $request
     * @return JsonResponse
     */
    public function store(StoreDesignationRequest $request)
    {
        $designation = Designation::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success retrieved designations',
            'data' => $designation
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Models\Designation $designation
     * @return JsonResponse
     */
    public function show(ShowDesignationRequest $request, Designation $designation): JsonResponse
    {
        return response()->json([
            'message' => 'Success retrieved designation',
            'data' => $designation
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDesignationRequest $request
     * @param Designation $designation
     * @return \Illuminate\Http\Response
     */
    public function update(UpdatedesignationRequest $request, Designation $designation): JsonResponse
    {
        $designation->update($request->validated());

        return response()->json([
            'message' => 'Success updated designation',
            'data' => $designation
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Designation $designation
     * @return JsonResponse
     */
    public function destroy(DeleteDesignationRequest $request, Designation $designation)
    {
        $designation->delete();

        return response()->json([
            'message' => 'Success deleted designation',
            'data' => $designation
        ]);
    }
}
