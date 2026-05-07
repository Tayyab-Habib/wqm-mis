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
        $roles = Role::query()
            ->select(['id', 'name'])
            ->get();

        if (0 === $roles->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieved roles with permissions',
            'data' => $roles,
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
        $role->update($request->validated());

        if ($role->wasChanged()) {
            return response()->json([
                'message' => 'Success updating role',
                'data' => $role
            ]);
        }
        return response()->json([
            'message' => 'Error updating role'
        ], SymfonyResponse::HTTP_BAD_REQUEST);
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

        return response()->json([
            'message' => 'Success deleting role',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
