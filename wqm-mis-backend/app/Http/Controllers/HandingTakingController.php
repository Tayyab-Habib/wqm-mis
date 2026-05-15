<?php

namespace App\Http\Controllers;

use App\Enums\AssetLogStatusEnum;
use App\Enums\IssueTypeEnum;
use App\Enums\MaterialLogStatusEnum;
use App\Enums\MaterialStatusEnum;
use App\Http\Requests\HandingTaking\DeleteHandingTakingRequest;
use App\Http\Requests\HandingTaking\ShowHandingTakingRequest;
use App\Http\Requests\HandingTaking\StoreHandingTakingRequest;
use App\Http\Requests\HandingTaking\UpdateHandingTakingRequest;
use App\Http\Requests\HandingTaking\ViewHandingTakingRequest;
use App\Http\Resources\HandingTakingResource;
use App\Models\Asset\Asset;
use App\Models\Asset\LaboratoryAsset;
use App\Models\Asset\LaboratoryAssetLog;
use App\Models\HandingTaking;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\LaboratoryMaterialLog;
use App\Models\Material\Material;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HandingTakingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewHandingTakingRequest $request)
    {

        $authUser = auth()->user();
        $handingTakings = HandingTaking::query()
            ->when(!$authUser->isUnscoped(), fn(Builder $query) => $query->where('created_by', '=', $authUser->id))
            ->with([
                'laboratory:id,name',
                'createdByUser:id,name',
                'stockable' => function (MorphTo $morphTo) {
                    $morphTo->constrain([
                        LaboratoryAsset::class => function (Builder $query) {
                            $query->with('asset:id,name');
                        },
                        LaboratoryMaterial::class => function (Builder $query) {
                            $query->with('material:id,name');
                        },
                    ]);
                },
                'assignedTo:id,name',
            ])
            ->get();

        if ($handingTakings->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching handing-takings',
            'data' => HandingTakingResource::collection($handingTakings),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreHandingTakingRequest $request
     * @return JsonResponse
     */
    public function store(StoreHandingTakingRequest $request)
    {
        $validatedData = $request->validated();

        try {
            $laboratory = auth()->user()->laboratoryUser;

            $laboratoryUsers = User::query()
                ->select(['id', 'name', 'designation_id'])
                ->with('designation:id,name')
                ->whereHas('laboratories', fn($query) => $query->where('laboratory_id', '=', $laboratory->id))
                ->get();

            if (!$laboratoryUsers->contains('id', $validatedData['laboratory_user_id'])) {
                return response()->json([
                    'message' => 'Laboratory users is not belongs to your laboratory',
                    'data' => null
                ], SymfonyResponse::HTTP_BAD_REQUEST);
            }

            $stockableType = match ($validatedData['stockable_type']) {
                IssueTypeEnum::STOCK->value => LaboratoryMaterial::class,
                IssueTypeEnum::INVENTORY->value => LaboratoryAsset::class,
            };

            DB::beginTransaction();

            $handingableId = '';
            $handingableType = '';

            switch ($validatedData['stockable_type']) {
                case IssueTypeEnum::STOCK->value:
                    $handingableType = LaboratoryMaterialLog::class;
                    $laboratoryMaterial = $laboratory->laboratoryMaterials()
                        ->find($validatedData['stockable_id']);

                    if ($validatedData['quantity'] > $laboratoryMaterial->available_quantity) {
                        return response()->json([
                            'message' => "The selected quantity is not available in inventory",
                            'data' => null,
                        ], SymfonyResponse::HTTP_BAD_REQUEST);
                    }

                    $laboratoryMaterialLog = $laboratoryMaterial->laboratoryMaterialLogs()
                        ->create([
                            'quantity' => -$request->quantity,
                            'unit' => $laboratoryMaterial->unit,
                            'status' => MaterialLogStatusEnum::OUT,
                        ]);

                    $handingableId = $laboratoryMaterialLog->id;

                    $updatedQuantity = $laboratoryMaterial->laboratoryMaterialLogs()
                        ->sum('quantity');

                    $availableInventoryPercentage = $updatedQuantity / $laboratoryMaterial->quantity * 100;
                    $status = $availableInventoryPercentage < $laboratoryMaterial->threshold
                        ? MaterialStatusEnum::BELOW_THRESHOLD->value
                        : MaterialStatusEnum::ACTIVE->value;

                    if ($status === MaterialStatusEnum::BELOW_THRESHOLD->value) {
                        $data = [
                            'content' => 'You have a below threshold material ' . $laboratoryMaterialLog->materialLog->material->name,
                            'status' => MaterialStatusEnum::BELOW_THRESHOLD->value,
                            'name' => auth()->user()->name,
                        ];

                        //send notification to authenticated user
                        auth()->user()->notify(new GenericNotification($data));
                    }

                    $laboratoryMaterial->update([
                        'available_quantity' => $updatedQuantity,
                        'status' => $status,
                    ]);
                    break;
                case IssueTypeEnum::INVENTORY->value:
                    $handingableType = LaboratoryAssetLog::class;
                    $laboratoryAsset = $laboratory->laboratoryAssets()
                        ->find($validatedData['stockable_id']);

                    if ($validatedData['quantity'] > $laboratoryAsset->quantity) {
                        return response()->json([
                            'message' => "The selected quantity is not available in inventory",
                            'data' => null,
                        ], SymfonyResponse::HTTP_BAD_REQUEST);
                    }

                    $laboratoryAssetLog = $laboratoryAsset->laboratoryAssetLogs()
                        ->create([
                            'quantity' => -$request->quantity,
                            'unit' => $laboratoryAsset->unit,
                            'status' => AssetLogStatusEnum::OUT,
                        ]);

                    $handingableId = $laboratoryAssetLog->id;

                    $updatedQuantity = $laboratoryAsset->laboratoryAssetLogs()
                        ->sum('quantity');

//                    $status = $updatedQuantity < $laboratoryAsset->threshold
//                        ? MaterialStatusEnum::BELOW_THRESHOLD->value
//                        : MaterialStatusEnum::ACTIVE->value;
//
//                    if ($status === AssetStatusEnum::BELOW_THRESHOLD->value) {
//                        $data = [
//                            'content' => 'You have a below threshold asset ' . $laboratoryAssetLog->assetLog->asset->name,
//                            'status' => AssetStatusEnum::BELOW_THRESHOLD->value,
//                            'name' => auth()->user()->name,
//                        ];
//
//                        //send notification to authenticated user
//                        auth()->user()->notify(new GenericNotification($data));
//                    }

                    $laboratoryAsset->update([
                        'quantity' => $updatedQuantity,
//                        'status' => $status,
                    ]);
                    break;
            }

            $handingTaking = HandingTaking::query()
                ->create(array_merge($validatedData, [
                    'stockable_type' => $stockableType,
                    'assigned_to' => $validatedData['laboratory_user_id'],
                    'laboratory_id' => $laboratory->id,
                ]));

            $handingTaking->handingTakingLogs()
                ->create([
                    'user_id' => auth()->id(),
                    'handingable_type' => $handingableType,
                    'handingable_id' => $handingableId,
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Success creating handing-taking',
                'data' => $handingTaking,
            ], SymfonyResponse::HTTP_CREATED);
        } catch (\Exception $exception) {
            info($exception->getMessage());
            DB::rollBack();

            return response()->json([
                'message' => 'Error creating handing-taking',
                'data' => '',
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param ShowHandingTakingRequest $request
     * @param HandingTaking $handingTaking
     * @return JsonResponse
     */
    public function show(ShowHandingTakingRequest $request, HandingTaking $handingTaking)
    {
        $authUser = auth()->user();

        if (!$authUser->isUnscoped() && (int)$handingTaking->created_by !== auth()->id()) {
            return response()->json([
                'message' => 'You are not authorized to access this resource',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $handingTaking->load([
            'stockable' => function (MorphTo $morphTo) {
                $morphTo->constrain([
                    LaboratoryAsset::class => function (Builder $query) {
                        $query->with('asset:id,name');
                    },
                    LaboratoryMaterial::class => function (Builder $query) {
                        $query->with('material:id,name');
                    },
                ]);
            },
            'assignedTo:id,name',
        ]);

//        $handingTaking->stockable_type = ($handingTaking->stockable_type === Asset::class
//            ? IssueTypeEnum::INVENTORY->value
//            : IssueTypeEnum::STOCK->value);

        return response()->json([
            'message' => 'Success fetching handing-taking',
            'data' => new HandingTakingResource($handingTaking),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateHandingTakingRequest $request
     * @param HandingTaking $handingTaking
     * @return JsonResponse
     */
    public function update(UpdateHandingTakingRequest $request, HandingTaking $handingTaking)
    {
        $validatedData = $request->validated();

        if ((int)$handingTaking->created_by !== auth()->id()) {
            return response()->json([
                'message' => 'You are not authorized to access this resource',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $stockableType = match ($validatedData['stockable_type']) {
            IssueTypeEnum::STOCK->value => Material::class,
            IssueTypeEnum::INVENTORY->value => Asset::class,
        };

        $handingTaking->update([
            'description' => $validatedData['description'],
            'stockable_type' => $stockableType,
            'stockable_id' => $validatedData['stockable_id'],
            'quantity' => $validatedData['quantity'],
            'unit' => $validatedData['unit'],
        ]);

        if ($handingTaking->wasChanged()) {
            return response()->json([
                'message' => 'Success updating handing taking',
                'data' => $handingTaking
            ]);
        }

        return response()->json([
            'message' => 'Error updating handing taking'
        ], SymfonyResponse::HTTP_BAD_REQUEST);


    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteHandingTakingRequest $request
     * @param HandingTaking $handingTaking
     * @return JsonResponse
     */
    public function destroy(DeleteHandingTakingRequest $request, HandingTaking $handingTaking)
    {
        $handingTaking->delete();

        return response()->json([
            'message' => 'Success deleting handing-shaking',
            'data' => $handingTaking
        ], SymfonyResponse::HTTP_OK);
    }
}
