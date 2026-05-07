<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\CollectedInEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CollectedInController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching collected in statuses',
            'data' => CollectedInEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
