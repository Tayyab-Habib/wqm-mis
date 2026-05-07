<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\AssetStatusEnum;
use App\Enums\IssueStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IssueStatusController extends Controller
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
            'message' => 'Success fetching issue statuses',
            'data' => IssueStatusEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
