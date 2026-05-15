<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchClientRequest;
use App\Models\Client;
use App\Models\Scopes\LatestScope;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchClientController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchClientRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchClientRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $query = Client::query()
            ->when(!$authUser->isUnscoped(), function ($query) use ($authUser) {
                $query->whereHas('waterSamples', function ($query) use ($authUser) {
                    $query->where('created_by', '=', $authUser->id);
                });
            });


        if (isset($validatedData['search_by']) && $validatedData['search_by'] !== 'organization') {
            $query->where($validatedData['search_by'], 'like', '%' . $validatedData['query'] . '%');
        }

        if (isset($validatedData['organization_name'])) {
            $query->where('organization_name', '=', $validatedData['organization_name']);
        }

        $clients = $query->get();

        if (0 === $clients->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => '',
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving clients',
            'data' => $clients,
        ], SymfonyResponse::HTTP_OK);
    }

    public function organizations()
    {
        $authUser = auth()->user();
        $organizations = Client::query()
            ->select('organization_name')
            ->when(!$authUser->isUnscoped(), function ($query) use ($authUser) {
                $query->whereHas('waterSamples', function ($query) use ($authUser) {
                    $query->where('created_by', '=', $authUser->id);
                });
            })
            ->withoutGlobalScope(LatestScope::class)
            ->whereNotNull("organization_name")
            ->groupBy('organization_name')
            ->get();

        return response()->json([
            'message' => 'Success retrieving organizations',
            'data' => $organizations,
        ], SymfonyResponse::HTTP_OK);
    }
}
