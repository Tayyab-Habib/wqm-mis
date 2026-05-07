<?php

namespace App\Http\Controllers\Laboratories;

use App\Http\Controllers\Controller;
use App\Models\Laboratories\Laboratory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateLaboratoryStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param Laboratory $laboratory
     * @param boolean $isActive
     * @return JsonResponse
     */
    public function __invoke(Request $request, Laboratory $laboratory, bool $isActive)
    {
        $laboratory->update([
            'is_active' => $isActive,
            'modified_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Success updating laboratory status',
            'data' => $laboratory,
        ], SymfonyResponse::HTTP_OK);
    }
}
