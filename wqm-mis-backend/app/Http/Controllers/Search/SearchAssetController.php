<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchAssetRequest;
use App\Models\Asset\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchAssetController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchAssetRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchAssetRequest $request)
    {
        $validatedData = $request->validated();
        $query = Asset::query();

        if (isset($validatedData['name'])) {
            $query->whereFullText('name', $validatedData['name'] . '*', ['mode' => 'boolean']);
        }

        if (isset($validatedData['starting_threshold'], $validatedData['ending_threshold'])) {
            $query->whereBetween('threshold', [$validatedData['starting_threshold'], $validatedData['ending_threshold']]);
        }

        if (isset($validatedData['starting_date'], $validatedData['ending_date'])) {
            $query->whereBetween('date_of_entry', [$validatedData['starting_date'], $validatedData['ending_date']]);
        }

        if (isset($validatedData['status'])) {
            $query->where('status', '=', $validatedData['status']);
        }

        $assets = $query->paginate(20);

        if (0 === $assets->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $assets,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving assets',
            'data' => $assets,
        ], SymfonyResponse::HTTP_OK);
    }
}
