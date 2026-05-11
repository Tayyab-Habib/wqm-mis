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

        $materials = $query->laboratoryMaterials()
            ->withWhereHas('material:id,name')
            ->get();

        return LaboratoryMaterialResource::collection($materials);
    }

    public function fetchAll(): Collection
    {
        return LaboratoryMaterial::query()
            ->with([
                'material:id,name',
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
        if (!$this->authUser->hasRole('system-administrator') && $this->authUser->laboratoryUser->id !== (int)$laboratoryMaterial->laboratory_id) {
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
