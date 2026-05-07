<?php

namespace App\Http\Controllers;

use App\Http\Requests\WaterScheme\DeleteWaterSchemeRequest;
use App\Http\Requests\WaterScheme\ShowWaterSchemeRequest;
use App\Http\Requests\WaterScheme\StoreWaterSchemeRequest;
use App\Http\Requests\WaterScheme\UpdateWaterSchemeRequest;
use App\Http\Requests\WaterScheme\ViewWaterSchemeRequest;
use App\Models\WaterScheme;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSchemeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewWaterSchemeRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        $waterSchemes = WaterScheme::query()
             ->select([
                 'id',
                'slug',
                'name',
                'is_active',
                'source_type',
                'power_input',
                'union_council_id',
                'tehsil_id',
                'district_id',
                'division_id',
                 'created_at'
            ])
            ->with([
                'unionCouncil',
                'tehsil',
                'district',
                'division',
                'createdByUser:id,name',
            ])
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('division_id', '=', $authUser->district->division_id))
            ->get();


        if ($waterSchemes->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water schemes',
            'data' => $waterSchemes
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreWaterSchemeRequest $request
     * @return JsonResponse
     */
    public function store(StoreWaterSchemeRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $authUser = auth()->user();

//        if (!$authUser->hasRole('system-administrator') && $authUser->district_id != $validatedData['district_id']) {
//
//            return response()->json([
//                'message' => 'The selected district does not belong to user',
//                'data' => null,
//            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
//
//        }
        $waterScheme = WaterScheme::query()
            ->create($validatedData);

        return response()->json([
            'message' => 'Success creating water scheme',
            'data' => $waterScheme,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowWaterSchemeRequest $request
     * @param WaterScheme $waterScheme
     * @return JsonResponse
     */
    public function show(ShowWaterSchemeRequest $request, WaterScheme $waterScheme): JsonResponse
    {
        if (auth()->user()->district_id !== $waterScheme->district_id && !auth()->user()->hasRole('system-administrator')) {
            return response()->json([
                'message' => 'You do not have permission to view this Water Schemes',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Success fetching water scheme',
            'data' => $waterScheme->load([
                'unionCouncil',
                'tehsil',
                'district',
                'division',
                'province',
                'createdByUser:id,name',
                'modifiedByUser:id,name',
            ]),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWaterSchemeRequest $request
     * @param WaterScheme $waterScheme
     * @return JsonResponse
     */
    public function update(UpdateWaterSchemeRequest $request, WaterScheme $waterScheme): JsonResponse
    {
        $waterScheme->update($request->validated());

        return response()->json([
            'message' => 'Success updating water scheme',
            'data' => $waterScheme,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteWaterSchemeRequest $request
     * @param WaterScheme $waterScheme
     * @return JsonResponse
     */
    public function destroy(DeleteWaterSchemeRequest $request, WaterScheme $waterScheme): JsonResponse
    {
        $waterScheme->delete();

        return response()->json([
            'message' => 'Success deleting water scheme',
            'data' => $waterScheme,
        ], SymfonyResponse::HTTP_OK);
    }
}
