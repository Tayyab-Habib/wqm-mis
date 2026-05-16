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
        // RBAC: admin/manager/view-only-admin see all stock; everyone else is
        // lab-scoped via the existing laboratoryUser pivot.
        if ($this->authUser->hasRole(['system-administrator', 'system-manager', 'view-only-admin'])) {
            $materials = Material::query()->get();
        } else {
            $laboratoryId = $this->authUser->laboratoryUser?->id;
            if (!$laboratoryId) {
                return response()->json([
                    'message' => 'User has no laboratory assigned',
                    'data' => null,
                ], SymfonyResponse::HTTP_FORBIDDEN);
            }

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
        // Resolve current user's lab from the latest LaboratoryUser pivot row.
        // Without a lab we cannot create the per-lab allocation rows.
        $labId = $this->authUser->laboratoryDetails?->laboratory_id;
        if (!$labId) {
            return response()->json([
                'message' => 'Your account is not associated with a laboratory. Cannot allocate stock.',
                'data' => null,
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::beginTransaction();

            // 1. Master catalog row
            $material = Material::query()->create(array_merge(
                $request->validated(),
                ['available_quantity' => $request->quantity]
            ));

            // 2. Global ledger entry (status = IN)
            $materialLog = $material->materialLogs()->create([
                'user_id'        => auth()->id(),
                'date_of_expiry' => $request->date_of_expiry,
                'quantity'       => $request->quantity,
                'unit'           => $request->unit,
                'date_of_entry'  => now()->format('Y-m-d'),
                'status'         => MaterialLogStatusEnum::IN->value,
            ]);

            // 3. Per-lab allocation. quantity & threshold are VARCHAR in DB,
            // so we cast explicitly to avoid silent truncation.
            $laboratoryMaterial = LaboratoryMaterial::query()->create([
                'laboratory_id'      => $labId,
                'material_id'        => $material->id,
                'quantity'           => (string) $request->quantity,
                'available_quantity' => $request->quantity,
                'unit'               => $request->unit,
                'threshold'          => (string) $request->threshold,
                'status'             => $request->status,
            ]);

            // 4. Per-lab ledger entry, linked back to the global log
            $laboratoryMaterial->laboratoryMaterialLogs()->create([
                'material_log_id' => $materialLog->id,
                'date_of_expiry'  => $request->date_of_expiry,
                'quantity'        => (string) $request->quantity,
                'unit'            => $request->unit,
                'status'          => MaterialLogStatusEnum::IN->value,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Success creating stock',
                'data'    => $material->load('materialLogs', 'laboratoryMaterials.laboratoryMaterialLogs'),
            ], SymfonyResponse::HTTP_CREATED);

        } catch (\Exception $exception) {
            DB::rollBack();
            info($exception->getMessage());
            return response()->json([
                'error'   => 'Error creating stock',
                'message' => $exception->getMessage(),
                'data'    => null,
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

        if ($this->authUser->hasRole(['system-administrator', 'system-manager', 'view-only-admin'])) {
            $material->load('materialLogs');
        } else {
            $laboratoryId = $this->authUser->laboratoryUser?->id;
            if (!$laboratoryId) {
                return response()->json([
                    'message' => 'User has no laboratory assigned',
                    'data' => null,
                ], SymfonyResponse::HTTP_FORBIDDEN);
            }

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
        // Pull the per-lab sync hints out — they aren't columns on `materials`.
        $laboratoryMaterialId = $validatedData['laboratory_material_id'] ?? null;
        $dateOfExpiry         = $validatedData['date_of_expiry'] ?? null;
        unset($validatedData['laboratory_material_id'], $validatedData['date_of_expiry']);

        try {
            DB::beginTransaction();

            $material->update($validatedData);

            // Sync the per-lab allocation so the listing (which reads from
            // `laboratory_materials`) reflects the edited qty + threshold.
            if ($laboratoryMaterialId) {
                \App\Models\Material\LaboratoryMaterial::query()
                    ->where('id', $laboratoryMaterialId)
                    ->update([
                        'quantity'           => (string) ($validatedData['quantity']           ?? $material->quantity),
                        'available_quantity' => (string) ($validatedData['available_quantity'] ?? $material->available_quantity),
                        'threshold'          => (string) $validatedData['threshold'],
                        'unit'               => $validatedData['unit'] ?? $material->unit,
                        'status'             => $validatedData['status'],
                    ]);

                // Update the latest log row's expiry so the listing's
                // "earliest expiry" reflects the new date. Frontend reduces
                // over all logs to pick the earliest, so updating the most
                // recent IN log is the right scope.
                \App\Models\Material\LaboratoryMaterialLog::query()
                    ->where('laboratory_material_id', $laboratoryMaterialId)
                    ->orderByDesc('id')
                    ->limit(1)
                    ->update(['date_of_expiry' => $dateOfExpiry]);
            }

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
