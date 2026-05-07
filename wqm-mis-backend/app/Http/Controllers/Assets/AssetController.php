<?php

namespace App\Http\Controllers\Assets;

use App\Enums\AssetLogStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\DeleteAssetRequest;
use App\Http\Requests\Asset\ShowAssetRequest;
use App\Http\Requests\Asset\StoreAssetRequest;
use App\Http\Requests\Asset\UpdateAssetRequest;
use App\Http\Requests\Asset\ViewAssetRequest;
use App\Models\Asset\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewAssetRequest $request): JsonResponse
    {
        $assets = Asset::query()->get();

        if ($assets->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching inventories',
            'data' => $assets
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreAssetRequest $request
     * @return JsonResponse
     */
    public function store(StoreAssetRequest $request): JsonResponse
    {
        try {
            $validatedData = $request->validated();
            $validatedData = array_merge($validatedData, [
                'user_id' => auth()->id(),
                'date_of_entry' => now()->format('Y-m-d'),
            ]);

            DB::beginTransaction();
            $asset = Asset::query()
                ->create($validatedData);

            $validatedData = array_merge($validatedData, [
                'status' => AssetLogStatusEnum::IN->value,
            ]);

            $asset->assetLogs()
                ->create($validatedData);

            DB::commit();
            return response()->json([
                'message' => 'Success creating inventory',
                'data' => $asset
            ], SymfonyResponse::HTTP_CREATED);

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
            return response()->json([
                'message' => 'Error creating inventory',
                'data' => ''
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param ShowAssetRequest $request
     * @param Asset $asset
     * @return JsonResponse
     */
    public function show(ShowAssetRequest $request, Asset $asset): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching inventory',
            'data' => $asset->load('assetLogs')
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateAssetRequest $request
     * @param Asset $asset
     * @return JsonResponse
     */
    public function update(UpdateAssetRequest $request, Asset $asset): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();
//            $asset->assetLogs()
//                ->where('status', '=', AssetLogStatusEnum::IN)
//                ->orderBy('id', 'desc')
//                ->first()
//                ->update($validatedData);
//
//            $assetLogsSum = $asset->assetLogs()
//                ->sum('quantity');
//
//            $validatedData = array_merge($validatedData, ['quantity' => $assetLogsSum]);

            $asset->update($validatedData);

            DB::commit();

            return response()->json([
                'message' => 'Success updating inventory',
                'data' => $asset
            ], SymfonyResponse::HTTP_OK);

        } catch (\Exception $exception) {

            info($exception->getMessage());
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating inventory',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteAssetRequest $request
     * @param Asset $asset
     * @return JsonResponse
     */
    public function destroy(DeleteAssetRequest $request, Asset $asset): JsonResponse
    {
        $asset->delete();

        return response()->json([
            'message' => 'Success deleting inventory',
            'data' => $asset
        ], SymfonyResponse::HTTP_OK);
    }
}
