<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleHasPermissionRequest;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RoleHasPermissionController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param Role $role
     * @return JsonResponse
     */
    public function __invoke(RoleHasPermissionRequest $request, Role $role): JsonResponse
    {
        $modules = Module::query()
            ->orderBy('name', 'asc')
            ->rolesPermissions('defaultPermissions', $role->id)
            ->rolesPermissions('customPermissions', $role->id)
            ->get();

        if (0 === $modules->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving module permissions',
            'data' => $modules,
        ], SymfonyResponse::HTTP_OK);
    }
}
