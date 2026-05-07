<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DesignationController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {

        $designations = Designation::query()
            ->select(['id', 'name'])
            ->get();

        return response()->json([
            'message' => 'Success fetching designations',
            'data' => $designations
        ], SymfonyResponse::HTTP_OK);
    }
}
