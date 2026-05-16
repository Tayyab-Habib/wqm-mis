<?php

namespace App\Services;

use App\Http\Resources\LaboratoryAssetResource;
use App\Models\Asset\LaboratoryAsset;
use App\Models\Laboratories\Laboratory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FetchAssetService
{
    private $authUser;

    public function __construct()
    {
        $this->authUser = auth()->user();
    }

    /**
     * Display a listing of the materials related to laboratory.
     *
     * @param Laboratory $laboratory
     * @return mixed
     */
    public function fetch(Laboratory $laboratory = null)
    {
        $query = $this->authUser->laboratoryUser;

        if ($laboratory) {
            $query = $laboratory;
        }

        // Eager-load the master asset so the resource can read kind/category/etc.
        $materials = $query->laboratoryAssets()
            ->with(['asset:id,name,kind,category,item_code,condition,date_of_purchase,purchase_value,location,last_verified,remarks,specification,country,agency'])
            ->get();

        return LaboratoryAssetResource::collection($materials);
    }

    public function fetchAll(): Collection
    {
        return LaboratoryAsset::query()
            ->with([
                // Eager-load all SRS §2.7-2/§2.7-3 fields from the master asset.
                'asset:id,name,kind,category,item_code,condition,date_of_purchase,purchase_value,location,last_verified,remarks,status,specification,country,agency',
                'laboratory:id,name',
                'laboratoryAssetLogs' => function ($query) {
                    $query->orderByDesc('id')->with('recipientLab:id,name');
                },
            ])
            ->get();
    }

    /**
     * Display a specific asset related to laboratory.
     *
     * @param LaboratoryAsset $laboratoryAsset
     * @return JsonResponse
     */
    public function show(LaboratoryAsset $laboratoryAsset): JsonResponse
    {
        // RBAC: admin/manager/view-only-admin + hierarchy roles see any asset.
        // Lab roles can only access assets in their lab.
        $unrestrictedRoles = ['system-administrator', 'system-manager', 'view-only-admin', 'chief-engineer', 'superintending-engineer', 'xen'];
        $userLabId = optional($this->authUser->laboratoryUser)->id;
        if (!$this->authUser->hasAnyRole($unrestrictedRoles) && $userLabId !== (int)$laboratoryAsset->laboratory_id) {
            return response()->json([
                'message' => 'You are not authorize to access data',
                'data' => '',
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $laboratoryAsset->load([
            'asset',
            'laboratoryAssetLogs'
        ]);

        return response()->json([
            'message' => 'Success fetching laboratory asset',
            'data' => (new LaboratoryAssetResource($laboratoryAsset))
        ]);
    }
}
