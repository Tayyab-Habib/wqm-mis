<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchLaboratoryRequest;
use App\Models\Laboratories\Laboratory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchLaboratoryController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchLaboratoryRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchLaboratoryRequest $request)
    {
        $validatedData = $request->validated();
        $query = Laboratory::query();

        if (isset($validatedData['name'])) {
            $query->whereFullText('name', $validatedData['name'] . '*', ['mode' => 'boolean']);
        }


        if (isset($validatedData['phone'])) {
            $query->where('phone', '=', $validatedData['phone']);
        }

        if (isset($validatedData['address'])) {
            $query->whereFullText('address', $validatedData['address'] . '*', ['mode' => 'boolean']);
        }

        if (isset($validatedData['union_council_id'])) {
            $query->where('union_council_id', '=', $validatedData['union_council_id']);
        }

        if (isset($validatedData['tehsil_id'])) {
            $query->where('tehsil_id', '=', $validatedData['tehsil_id']);
        }

        if (isset($validatedData['district_id'])) {
            $query->where('district_id', '=', $validatedData['district_id']);
        }

        if (isset($validatedData['division_id'])) {
            $query->where('division_id', '=', $validatedData['division_id']);
        }

        if (isset($validatedData['province_id'])) {
            $query->where('province_id', '=', $validatedData['province_id']);
        }


        $laboratories = $query->paginate(20);

        if (0 === $laboratories->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $laboratories,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving laboratories',
            'data' => $laboratories,
        ], SymfonyResponse::HTTP_OK);
    }
}
