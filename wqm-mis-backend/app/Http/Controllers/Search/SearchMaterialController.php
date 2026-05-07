<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchMaterialRequest;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchMaterialController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchMaterialRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchMaterialRequest $request)
    {
        $validatedData = $request->validated();
        $query = Material::query();

        if (isset($validatedData['name'])) {
            $query->whereFullText('name', $validatedData['name'] . '*', ['mode' => 'boolean']);
        }

        if (isset($validatedData['starting_threshold'], $validatedData['ending_threshold'])) {
            $query->whereBetween('threshold', [$validatedData['starting_threshold'], $validatedData['ending_threshold']]);
        }

        if (isset($validatedData['status'])) {
            $query->where('status', '=', $validatedData['status']);
        }

        $materials = $query->paginate(20);

        if (0 === $materials->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $materials,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving materials',
            'data' => $materials,
        ], SymfonyResponse::HTTP_OK);
    }
}
