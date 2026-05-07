<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ShowInventoryLogRequest;
use App\Models\Inventory\InventoryLog;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryLogController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param InventoryLog $inventoryLog
     * @return JsonResponse
     */
    public function show(ShowInventoryLogRequest $request, InventoryLog $inventoryLog)
    {
        return response()->json([
            'message' => 'Success showing inventory-log',
            'data' => $inventoryLog->load('inventoryDetail')
        ], SymfonyResponse::HTTP_OK);
    }
}
