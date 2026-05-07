<?php

namespace App\Http\Controllers\Assets;

use App\Enums\AssetLogStatusEnum;
use App\Enums\AssetStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Asset\StoreAssetLogRequest;
use App\Http\Requests\Asset\UpdateAssetLogRequest;
use App\Models\Asset\Asset;
use App\Models\Asset\AssetLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssetLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  StoreAssetLogRequest  $request
     * @return JsonResponse
     */
    public function store(StoreAssetLogRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        try {
            DB::beginTransaction();

            $assetLog = AssetLog::query()
                ->create(array_merge($validatedData, [
                    'user_id' => auth()->id(),
                    'date_of_entry' => now()->format('Y-m-d'),
                    'status' => AssetLogStatusEnum::IN->value,
                ]));

            $assetLogsSum = AssetLog::query()
                ->where('asset_id', '=', $validatedData['asset_id'])
                ->sum('quantity');

            $status = $assetLogsSum < $assetLog->asset->thresh_hold
                ? AssetStatusEnum::BELOW_THRESHOLD->value
                : AssetStatusEnum::ACTIVE->value;

            Asset::query()
                ->find($request->asset_id)
                ->update([
                    'quantity' => $assetLogsSum,
                    'status' => $status,
                    'date_of_expiry' => $validatedData['date_of_expiry']
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Success creating asset stock',
                'data' => $assetLog
            ], SymfonyResponse::HTTP_CREATED);
        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating asset stock',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);

        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Asset\AssetLog  $assetLog
     * @return JsonResponse
     */
    public function show(AssetLog $assetLog): JsonResponse
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateAssetLogRequest  $request
     * @param  \App\Models\Asset\AssetLog  $assetLog
     * @return JsonResponse
     */
    public function update(UpdateAssetLogRequest $request, AssetLog $assetLog): JsonResponse
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Asset\AssetLog  $assetLog
     * @return JsonResponse
     */
    public function destroy(AssetLog $assetLog): JsonResponse
    {
        //
    }
}
