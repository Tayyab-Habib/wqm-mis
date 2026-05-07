<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnionCouncil\DeleteUnionCouncilRequest;
use App\Http\Requests\UnionCouncil\ShowUnionCouncilRequest;
use App\Http\Requests\UnionCouncil\StoreUnionCouncilRequest;
use App\Http\Requests\UnionCouncil\UpdateUnionCouncilRequest;
use App\Http\Requests\UnionCouncil\ViewUnionCouncilRequest;
use App\Models\UnionCouncil;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UnionCouncilController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewUnionCouncilRequest $request)
    {
        $unionCouncils = UnionCouncil::query()
            ->when($request->tehsil_id, fn($query) => $query->where('tehsil_id', $request->tehsil_id))
            ->with([
                'tehsil:id,name,district_id' => [
                    'district.division:id,name,province_id' => [
                        'province:id,name'
                    ]
                ]
            ])->get();

        if ($unionCouncils->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching union councils',
            'data' => $unionCouncils
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreUnionCouncilRequest $request
     * @return JsonResponse
     */
    public function store(StoreUnionCouncilRequest $request)
    {
        $unionCouncil = UnionCouncil::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating union council',
            'data' => $unionCouncil,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param UnionCouncil $unionCouncil
     * @return JsonResponse
     */
    public function show(ShowUnionCouncilRequest $request, UnionCouncil $unionCouncil)
    {
        return response()->json([
            'message' => 'Success fetching union council',
            'data' => $unionCouncil->load([
                'tehsil:id,name,district_id' => [
                    'district.division:id,name,province_id' => [
                        'province:id,name'
                    ]
                ]
            ])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUnionCouncilRequest $request
     * @param UnionCouncil $unionCouncil
     * @return JsonResponse
     */
    public function update(UpdateUnionCouncilRequest $request, UnionCouncil $unionCouncil)
    {
        $unionCouncil->update($request->validated());

        if ($unionCouncil->wasChanged()) {
            return response()->json([
                'message' => 'Success updating union council',
                'data' => $unionCouncil
            ]);
        }

        return response()->json([
            'message' => 'Error updating union council'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param UnionCouncil $unionCouncil
     * @return JsonResponse
     */
    public function destroy(DeleteUnionCouncilRequest $request, UnionCouncil $unionCouncil)
    {
        $unionCouncil->delete();

        return response()->json([
            'message' => 'Success deleting union council',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
