<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRolePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssignRolePermissionController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param AssignRolePermissionRequest $request
     * @return JsonResponse
     */
    public function __invoke(AssignRolePermissionRequest $request, Role $role)
    {
        $role->syncPermissions($request->permission_ids);

        return response()->json([
            'message' => 'Success assigning  permissions to ' . $role->name,
            'data' => null,
        ], SymfonyResponse::HTTP_OK);

    }
}
