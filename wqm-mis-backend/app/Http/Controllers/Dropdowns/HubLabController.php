<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\HubLab;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HubLabController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $hubLabs = HubLab::query()
            ->when($request->division_id, fn($query) => $query->where('division_id', $request->division_id))
            ->select('id', 'name', 'division_id')
            ->get();

        return response()->json([
            'message' => 'Success fetching hub labs',
            'data' => $hubLabs
        ], SymfonyResponse::HTTP_OK);
    }
}
