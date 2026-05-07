<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\EmploymentStatusEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EmployementStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'message' => 'Success fetching employment statuses',
            'data' => EmploymentStatusEnum::array(),
        ], SymfonyResponse::HTTP_OK);
    }
}
