<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryDetailStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryReceivedRequest;
use App\Models\Inventory\InventoryDetail;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryReceivedController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param InventoryReceivedRequest $request
     * @return JsonResponse
     */
    public function __invoke(InventoryReceivedRequest $request, InventoryDetail $inventoryDetail, bool $isReceived)
    {
        if ($inventoryDetail->status->value !== InventoryDetailStatusEnum::ISSUED->value) {
            return response()->json([
                'message' => 'Inventory status is not issued yet.',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if ($inventoryDetail->is_received) {
            return response()->json([
                'message' => 'Inventory already received.',
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $inventoryDetail->update([
            'is_received' => $isReceived,
            'received_at' => now()->format('Y-m-d')
        ]);

        if ($inventoryDetail->wasChanged()) {
            $data = [
                'content' => 'Inventory has been successfully delivered',
                'status' => 'received',
                'name' => auth()->user()->name,
            ];

            $systemAdministrators = User::query()->whereHas('roles', fn($query) => $query->where('name', '=', 'system-administrator'))->get();
            Notification::send($systemAdministrators, new GenericNotification($data));

            return response()->json([
                'message' => 'Success receiving Inventory',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Inventory not received.',
            'data' => null,
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }
}
