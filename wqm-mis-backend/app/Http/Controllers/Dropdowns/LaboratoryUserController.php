<?php

namespace App\Http\Controllers\Dropdowns;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryUserController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request): JsonResponse
    {
        $laboratoryUsers = User::query()
            ->select(['id', 'name', 'designation_id'])
            ->with('designation:id,name')
            ->whereHas('laboratories', fn($query) => $query->where('laboratory_id', '=', auth()->user()->laboratoryUser->id))
            ->get();

        return response()->json([
            'message' => 'Success fetching laboratories',
            'data' => $laboratoryUsers
        ], SymfonyResponse::HTTP_OK);
    }
}
