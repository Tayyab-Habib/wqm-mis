<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProvinceController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $provinces = Province::query()
            ->select('id', 'name')
            ->get();

        return response()->json([
            'message' => 'Success fetching provinces',
            'data' => $provinces,
        ], SymfonyResponse::HTTP_OK);
    }
}
