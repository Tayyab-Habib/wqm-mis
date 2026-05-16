<?php

namespace App\Services;

use App\Http\Resources\LaboratoryMaterialResource;
use App\Models\Laboratories\Laboratory;
use App\Models\Material\LaboratoryMaterial;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class FetchMaterialService
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

        // Eager-load the full set of master columns the frontend Edit modal needs
        // (category, supplier, unit, threshold) — not just id+name.
        $materials = $query->laboratoryMaterials()
            ->withWhereHas('material', function ($q) {
                $q->select(['id', 'name', 'category', 'unit', 'supplier', 'threshold']);
            })
            ->get();

        return LaboratoryMaterialResource::collection($materials);
    }

    public function fetchAll(): Collection
    {
        // Lab scoping: system-administrator sees all labs (admin lab-filter UI
        // depends on this); everyone else only sees stock at their own lab.
        $isAdmin       = $this->authUser?->isUnscoped() ?? false;
        $laboratoryId  = $this->authUser?->laboratoryUser?->id;

        return LaboratoryMaterial::query()
            ->when(!$isAdmin && $laboratoryId, fn ($q) => $q->where('laboratory_id', $laboratoryId))
            // Non-admin user with no lab linkage → return nothing rather than leaking everything.
            ->when(!$isAdmin && !$laboratoryId, fn ($q) => $q->whereRaw('1 = 0'))
            ->with([
                // Pull the full set of master columns the frontend Edit modal needs.
                'material:id,name,category,unit,supplier,threshold',
                'laboratory:id,name',
                'laboratoryMaterialLogs' => function ($query) {
                    $query->orderByDesc('id')->with('recipientLab:id,name');
                },
            ])
            ->get();
    }

    /**
     * Display a specific material related to laboratory.
     *
     * @param LaboratoryMaterial $laboratoryMaterial
     * @return LaboratoryMaterial
     */
    public function show(LaboratoryMaterial $laboratoryMaterial): JsonResponse
    {
        if (!$this->authUser->isUnscoped() && $this->authUser->laboratoryUser->id !== (int)$laboratoryMaterial->laboratory_id) {
            return response()->json([
                'message' => 'You are not authorize to access data',
                'data' => '',
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        $laboratoryMaterial->load([
            'material',
            'laboratoryMaterialLogs'
        ]);

        return response()->json([
            'message' => 'Success fetching laboratory material',
            'data' => (new LaboratoryMaterialResource($laboratoryMaterial))
        ]);
    }
}
