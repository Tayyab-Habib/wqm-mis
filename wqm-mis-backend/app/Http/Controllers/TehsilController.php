<?php

namespace App\Http\Controllers;

use App\Http\Requests\Tehsil\DeleteTehsilRequest;
use App\Http\Requests\Tehsil\ShowTehsilRequest;
use App\Http\Requests\Tehsil\StoreTehsilRequest;
use App\Http\Requests\Tehsil\UpdateTehsilRequest;
use App\Http\Requests\Tehsil\ViewTehsilRequest;
use App\Models\Tehsil;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TehsilController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewTehsilRequest $request)
    {
        $tehsils = Tehsil::query()
            ->when($request->district_id, fn($query) => $query->where('district_id', $request->district_id))
            ->with([
                'district.division:id,name,province_id' => [
                    'province:id,name'
                ]
            ])->get();

        if ($tehsils->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching tehsils',
            'data' => $tehsils
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTehsilRequest $request
     * @return JsonResponse
     */
    public function store(StoreTehsilRequest $request)
    {
        $tehsil = Tehsil::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating tehsil',
            'data' => $tehsil,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowTehsilRequest $request
     * @param Tehsil $tehsil
     * @return JsonResponse
     */
    public function show(ShowTehsilRequest $request, Tehsil $tehsil)
    {
        return response()->json([
            'message' => 'Success fetching tehsil',
            'data' => $tehsil->load([
                'district.division:id,name,province_id' => [
                    'province:id,name'
                ]
            ])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTehsilRequest $request
     * @param Tehsil $tehsil
     * @return JsonResponse
     */
    public function update(UpdateTehsilRequest $request, Tehsil $tehsil)
    {
        $tehsil->update($request->validated());

        if ($tehsil->wasChanged()) {
            return response()->json([
                'message' => 'Success updating tehsil',
                'data' => $tehsil
            ]);
        }
        return response()->json([
            'message' => 'Error updating tehsil'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteTehsilRequest $request
     * @param Tehsil $tehsil
     * @return JsonResponse
     */
    public function destroy(DeleteTehsilRequest $request, Tehsil $tehsil)
    {
        if ($tehsil->loadExists('unionCouncils')->union_councils_exists) {
            return response()->json([
                'message' => 'Error deleting tehsil, delete all union councils belonging to this tehsil first',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }
        $tehsil->delete();

        return response()->json([
            'message' => 'Success deleting tehsil',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
