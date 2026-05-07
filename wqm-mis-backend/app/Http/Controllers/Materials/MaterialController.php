<?php

namespace App\Http\Controllers\Materials;

use App\Enums\MaterialLogStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Material\DeleteMaterialRequest;
use App\Http\Requests\Material\ShowMaterialRequest;
use App\Http\Requests\Material\StoreMaterialRequest;
use App\Http\Requests\Material\UpdateMaterialRequest;
use App\Http\Requests\Material\ViewMaterialRequest;
use App\Http\Resources\LaboratoryMaterialResource;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MaterialController extends Controller
{
    private $authUser;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->authUser = auth()->user();
            return $next($request);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewMaterialRequest $request): JsonResponse
    {
        if ($this->authUser->hasRole(['system-administrator', 'system-manager'])) {
            $materials = Material::query()->get();
        } else {
            $laboratoryId = $this->authUser->laboratoryUser->id;

            $materials = LaboratoryMaterial::query()
                ->where('laboratory_id', '=', $laboratoryId)
                ->with('material:id,name')
                ->get();

            $materials->data = LaboratoryMaterialResource::collection($materials);
        }

        if ($materials->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching stocks',
            'data' => $materials
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreMaterialRequest $request
     * @return JsonResponse
     */
    public function store(StoreMaterialRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $material = Material::query()
                ->create(array_merge($request->validated(), ['available_quantity'=> $request->quantity]));

            $material->materialLogs()
                ->create([
                    'user_id' => auth()->id(),
                    'date_of_expiry' => $request->date_of_expiry,
                    'quantity' => $request->quantity,
                    'unit' => $request->unit,
                    'date_of_entry' => now()->format('Y-m-d'),
                    'status' => MaterialLogStatusEnum::IN->value,
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Success creating stock',
                'data' => $material
            ], SymfonyResponse::HTTP_CREATED);

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
            return response()->json([
                'error' => 'Error creating stock',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param ShowMaterialRequest $request
     * @param Material $material
     * @return JsonResponse
     */
    public function show(ShowMaterialRequest $request, Material $material): JsonResponse
    {

        if ($this->authUser->hasRole(['system-administrator', 'system-manager'])) {
            $material->load('materialLogs');
        } else {
            $laboratoryId = $this->authUser->laboratoryUser->id;

//            TODO: paginate material and laboratory material logs
            $material = $material->laboratoryMaterials()
                ->where('laboratory_id', '=', $laboratoryId)
                ->with([
                    'material:id,name',
                    'laboratoryMaterialLogs'
                ])
                ->first();

            if (!$material) {
                return response()->json([
                    'message' => 'Error user unauthorized',
                    'data' => null,
                ]);
            }

            $material = new LaboratoryMaterialResource($material);
        }
        return response()->json([
            'message' => 'Success fetching stock',
            'data' => $material
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateMaterialRequest $request
     * @param Material $material
     * @return JsonResponse
     */
    public function update(UpdateMaterialRequest $request, Material $material): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();

//            $material->materialLogs()
//                ->where('status', '=', MaterialLogStatusEnum::IN)
//                ->orderBy('id', 'desc')
//                ->first()
//                ->update($validatedData);
//
//            $materialLogsSum = $material->materialLogs()
//                ->sum('quantity');
//
//            $validatedData = array_merge($validatedData, ['quantity' => $materialLogsSum]);

            $material->update($validatedData);

            DB::commit();

            return response()->json([
                'message' => 'Success updating stock',
                'data' => $material
            ], SymfonyResponse::HTTP_OK);

        } catch (\Exception $exception) {
            info($exception->getMessage());

            DB::rollBack();

            return response()->json([
                'message' => 'Error updating stock',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteMaterialRequest $request
     * @param Material $material
     * @return JsonResponse
     */
    public function destroy(DeleteMaterialRequest $request, Material $material): JsonResponse
    {
        $material->delete();

        return response()->json([
            'message' => 'Success deleting stock',
            'data' => null,
        ], SymfonyResponse::HTTP_OK);
    }
}
