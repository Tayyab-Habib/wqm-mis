<?php

namespace App\Http\Controllers;

use App\Http\Requests\Division\DeleteDivisionRequest;
use App\Http\Requests\Division\ShowDivisionRequest;
use App\Http\Requests\Division\StoreDivisionRequest;
use App\Http\Requests\Division\UpdateDivisionRequest;
use App\Http\Requests\Division\ViewDivisionRequest;
use App\Models\Division;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DivisionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewDivisionRequest $request)
    {
        $divisions = Division::query()
            ->with('province:id,name')
            ->get();

        if ($divisions->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching divisions',
            'data' => $divisions
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreDivisionRequest $request
     * @return JsonResponse
     */
    public function store(StoreDivisionRequest $request)
    {
        $division = Division::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating division',
            'data' => $division,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param Division $division
     * @return JsonResponse
     */
    public function show(ShowDivisionRequest $request, Division $division)
    {
        return response()->json([
            'message' => 'Success fetching division',
            'data' => $division->load('province:id,name')
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDivisionRequest $request
     * @param Division $division
     * @return JsonResponse
     */
    public function update(UpdateDivisionRequest $request, Division $division)
    {
        $division->update($request->validated());

        if ($division->wasChanged()) {
            return response()->json([
                'message' => 'Success updating division',
                'data' => $division
            ]);
        }
        return response()->json([
            'message' => 'Error updating division'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteDivisionRequest $request
     * @param Division $division
     * @return JsonResponse
     */
    public function destroy(DeleteDivisionRequest $request, Division $division)
    {
        if ($division->loadExists('districts')->districts_exists) {
            return response()->json([
                'message' => 'Error deleting division, delete all districts belonging to this division first',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $division->delete();

        return response()->json([
            'message' => 'Success deleting division',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
