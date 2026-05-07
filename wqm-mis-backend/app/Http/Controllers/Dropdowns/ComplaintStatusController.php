<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\ComplaintStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ComplaintStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        //TODO: Sir Ahsan responsible for this
        return response()->json([
            'message' => 'Success fetching employment statuses',
            'data' => ComplaintStatusEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
