<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryDetailStatusEnum;
use App\Enums\InventoryStatusEnum;
use App\Enums\IssueTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\DeleteInventoryRequest;
use App\Http\Requests\Inventory\ShowInventoryRequest;
use App\Http\Requests\Inventory\StoreInventoryRequest;
use App\Http\Requests\Inventory\ViewInventoryRequest;
use App\Models\Asset\Asset;
use App\Models\Inventory\Inventory;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\AuthScope;
use Exception;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewInventoryRequest $request)
    {
        $authUser = auth()->user();
        // RBAC: SA/manager/view-only see all; lab roles filter via pivot;
        // CE/SE/XEN see all (hierarchy scope doesn't apply to demands).
        $invQuery = Inventory::query();
        $invQuery = AuthScope::inventories($invQuery, $authUser);
        $inventories = $invQuery
            ->with([
                'laboratory:id,name',
                'inventoryDetails:id,inventory_id,inventoryable_id,inventoryable_type,quantity,approved_quantity,unit,status,is_received,received_at' => [
                    'inventoryable:id,name'
                ]
            ])
            ->paginate(20);

        if (0 === $inventories->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        // Attach the issuing lab's effective available qty (available_quantity
        // minus expired-batch qty) to each detail so the approver can see what
        // they can actually fulfill before clicking Issue.
        $sourceLabId = $authUser->laboratoryDetails?->laboratory_id;
        if ($sourceLabId) {
            $today = now()->toDateString();
            foreach ($inventories->items() as $inv) {
                foreach ($inv->inventoryDetails as $det) {
                    if ($det->inventoryable_type !== Material::class) {
                        $det->central_available_qty = null;
                        continue;
                    }
                    $lm = \App\Models\Material\LaboratoryMaterial::query()
                        ->where('laboratory_id', $sourceLabId)
                        ->where('material_id', $det->inventoryable_id)
                        ->first();
                    if (!$lm) {
                        $det->central_available_qty = 0;
                        continue;
                    }
                    $expired = \App\Models\Material\LaboratoryMaterialLog::query()
                        ->where('laboratory_material_id', $lm->id)
                        ->where('status', 'in')
                        ->whereNotNull('date_of_expiry')
                        ->where('date_of_expiry', '<', $today)
                        ->sum('quantity');
                    $det->central_available_qty = max(0, (float) $lm->available_quantity - (float) $expired);
                }
            }
        }

        return response()->json([
            'message' => 'Success fetching inventories',
            'data' => $inventories
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreInventoryRequest $request
     * @return JsonResponse
     */
    public function store(StoreInventoryRequest $request)
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();

            $laboratoryId = auth()->user()->laboratoryUser?->id;

            if (!$laboratoryId) {
                return response()->json([
                    'message' => 'Error creating inventory, add laboratory to user first',
                    'data' => null,
                ], SymfonyResponse::HTTP_FORBIDDEN);
            }

            $inventory = Inventory::query()->create([
                'laboratory_id' => $laboratoryId,
                'urgency'       => $validatedData['urgency'] ?? 'routine',
                'justification' => $validatedData['justification'] ?? null,
            ]);

            foreach ($validatedData['details'] as $index => $_) {
                switch ($validatedData['details'][$index]['inventoryable_type']) {
                    case IssueTypeEnum::INVENTORY->value:
                        $inventoryableType = Asset::class;
                        break;
                    case IssueTypeEnum::STOCK->value:
                        $inventoryableType = Material::class;
                        break;
                }

                $inventory->inventoryDetails()->create([
                    'inventory_id' => $inventory->id,
                    'inventoryable_type' => $inventoryableType,
                    'inventoryable_id' => $validatedData['details'][$index]['inventoryable_id'],
                    'quantity' => $validatedData['details'][$index]['quantity'],
                    'unit' => $validatedData['details'][$index]['unit'],
                ]);

            }

            // notify system-administrator
            $data = [
                'content' => 'You have a new inventory request from ' . $inventory->laboratory->name,
                'status' => InventoryDetailStatusEnum::PENDING->value,
                'name' => auth()->user()->name,
            ];

            $systemAdministrators = User::query()
                ->whereHas('roles', function ($query) {
                    $query->where('name', '=', 'system-administrator');
                })
                ->get();

            // send notification to system administrator
            Notification::send($systemAdministrators, new GenericNotification($data));

            DB::commit();

            return response()->json([
                'message' => 'Success creating inventory',
                'data' => $inventory->load('inventoryDetails'),
            ], SymfonyResponse::HTTP_CREATED);

        } catch (Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating inventory',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param Inventory $inventory
     * @return JsonResponse
     */
    public function show(ShowInventoryRequest $request, Inventory $inventory)
    {
        if (!auth()->user()->isUnscoped()
            && (int)$inventory->created_by !== auth()->id()) {
            return response()->json([
                'message' => 'You are not authorize to access this inventory request',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'message' => 'Success showing inventory',
            'data' => $inventory->load([
                'laboratory:id,name',
                'inventoryDetails.inventoryable' => function (MorphTo $morphTo) {
                    $morphTo->constrain([
                        Asset::class => function ($query) {
                            $query->select(['id', 'name', 'quantity']);
                        },
                        Material::class => function ($query) {
                            $query->select(['id', 'name', 'available_quantity']);
                        },
                    ]);
                },
                'inventoryDetails.latestInventoryLog:id,inventory_logs.inventory_detail_id,comment',
                'createdByUser:id,name',
                'modifiedByUser:id,name',
            ])
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Inventory $inventory
     * @return JsonResponse
     */
    public function destroy(DeleteInventoryRequest $request, Inventory $inventory)
    {
        if ($inventory->status !== InventoryStatusEnum::PENDING->value
            || (int)$inventory->created_by !== auth()->id()) {
            return response()->json([
                'message' => 'You are not authorize to delete inventory request',
                'data' => null
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $inventory->delete();

        return response()->json([
            'message' => 'Success deleting inventory',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
