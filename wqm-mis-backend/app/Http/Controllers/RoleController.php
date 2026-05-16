<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\DeleteRoleRequest;
use App\Http\Requests\Role\IndexRoleRequest;
use App\Http\Requests\Role\ShowRoleRequest;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(IndexRoleRequest $request): JsonResponse
    {
        // Always return data as an array — never null — so the admin matrix
        // UI can render an empty list without special-casing. The previous
        // "No data to show" branch returned data:null which broke the
        // frontend's optimistic-append after the first create.
        $roles = Role::query()
            ->select(['id', 'name'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'message' => $roles->count() > 0 ? 'Success retrieving roles' : 'No data to show',
            'data'    => $roles,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRoleRequest $request
     * @return JsonResponse
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $validatedData = array_merge($request->validated(), ['guard_name' => 'web']);

        $role = Role::query()
            ->create($validatedData);

        // Spatie caches role/permission lookups per request — invalidate so
        // the next GET /roles call from the same admin session sees this
        // role immediately instead of a stale list.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json([
            'message' => 'Success creating role',
            'data' => $role,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        // Eager-load permissions so the admin Roles & Permissions matrix
        // can prefill toggle states on open. Returns just id + name on each.
        $role->load(['permissions:id,name']);
        return response()->json([
            'message' => 'Success fetching role',
            'data' => $role
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateRoleRequest $request
     * @param Role $role
     * @return JsonResponse
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        // UpdateRoleRequest has no rules() — accept name from the request
        // body directly. Validate uniqueness inline.
        $data = $request->only(['name']);
        if (!empty($data['name']) && $data['name'] !== $role->name) {
            $exists = Role::query()
                ->where('name', $data['name'])
                ->where('id', '!=', $role->id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'message' => 'A role with that name already exists.',
                ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }
        $role->update($data);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json([
            'message' => 'Success updating role',
            'data'    => $role,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Role $role
     * @return JsonResponse
     */
    public function destroy(DeleteRoleRequest $request, Role $role): JsonResponse
    {
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        return response()->json([
            'message' => 'Success deleting role',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
