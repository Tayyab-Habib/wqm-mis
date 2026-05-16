<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PermissionController extends Controller
{
    /**
     * List all permissions with their module info so the admin
     * Roles & Permissions matrix UI can group them by module.
     */
    public function index(Request $request): JsonResponse
    {
        // Auth gate — only admin-tier users may inspect the full permission
        // catalogue. Doesn't need a dedicated permission since SA / system-
        // manager / view-only-admin all qualify and the UI is admin-only.
        if (!auth()->user()?->isUnscoped()) {
            return response()->json([
                'message' => 'Not authorized to list permissions',
                'data'    => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $permissions = Permission::query()
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
            ->orderBy('modules.name')
            ->orderBy('permissions.name')
            ->get([
                'permissions.id',
                'permissions.name',
                'permissions.module_id',
                'modules.name as module_name',
            ])
            ->map(fn ($p) => [
                'id'        => $p->id,
                'name'      => $p->name,
                'module_id' => $p->module_id,
                'module'    => $p->module_name ? ['name' => $p->module_name] : null,
            ])
            ->values();

        return response()->json([
            'message' => 'Success retrieving permissions',
            'data'    => $permissions,
        ], SymfonyResponse::HTTP_OK);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'Not implemented — permissions are seeded, not created via API',
        ], SymfonyResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function show($id): JsonResponse
    {
        $permission = Permission::query()->find($id);
        if (!$permission) {
            return response()->json(['message' => 'Permission not found', 'data' => null], SymfonyResponse::HTTP_NOT_FOUND);
        }
        return response()->json(['message' => 'Success', 'data' => $permission], SymfonyResponse::HTTP_OK);
    }

    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => 'Not implemented — permission names are stable; rename not supported',
        ], SymfonyResponse::HTTP_NOT_IMPLEMENTED);
    }

    public function destroy($id)
    {
        return response()->json([
            'message' => 'Not implemented — permissions are required by code; deletion not supported',
        ], SymfonyResponse::HTTP_NOT_IMPLEMENTED);
    }
}
