<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClientListController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $authUser = auth()->user();

        $clients = Client::query()
            ->whereHas('waterSamples', function ($query) use ($authUser) {
                $query->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('created_by', '=', $authUser->id));
            })
            ->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching clients',
            'data' => $clients
        ], SymfonyResponse::HTTP_OK);
    }
}
