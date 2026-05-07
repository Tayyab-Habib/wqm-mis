<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RoleController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $roles = Role::query()
            ->when(!auth()->user()->hasRole('system-administrator'), fn($query) => $query->whereNot('name', 'system-administrator'))
            ->select(['id', 'name'])
            ->get();

        return response()->json([
            'message' => 'Success fetching roles',
            'data' => $roles
        ], SymfonyResponse::HTTP_OK);
    }
}
