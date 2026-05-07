<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\DesiredTestEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FocalPersonController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching focal persons',
            'data' => User::query()
                ->select(['id', 'name', 'district_id', 'designation_id'])
                ->with('designation:id,name')
                ->whereHas('roles', fn($query) => $query->whereIn('name', ['system-manager', 'laboratory-assistant']))
                ->get(),
        ], SymfonyResponse::HTTP_OK);
    }
}
