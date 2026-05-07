<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\ChamberEnum;
use App\Enums\OperationEnum;
use App\Enums\WssSourceTypeEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSchemeDropdownController extends Controller
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
            'message' => 'Success fetching water sample statuses',
            'data' => [
                'chambers' => ChamberEnum::array(),
                'operations' => OperationEnum::array(),
                'source_types' => WssSourceTypeEnum::array(),
            ],
        ], SymfonyResponse::HTTP_OK);
    }
}
