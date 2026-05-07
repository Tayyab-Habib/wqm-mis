<?php

namespace App\Http\Controllers;

use App\Http\Requests\Abbreviation\DeleteAbbreviationRequest;
use App\Http\Requests\Abbreviation\ShowAbbreviationRequest;
use App\Http\Requests\Abbreviation\StoreAbbreviationRequest;
use App\Http\Requests\Abbreviation\UpdateAbbreviationRequest;
use App\Http\Requests\Abbreviation\ViewAbbreviationRequest;
use App\Models\Abbreviation;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AbbreviationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewAbbreviationRequest $request): JsonResponse
    {
        $abbreviations = Abbreviation::query()->get();

        if ($abbreviations->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching abbreviations',
            'data' => $abbreviations
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreAbbreviationRequest $request
     * @return JsonResponse
     */
    public function store(StoreAbbreviationRequest $request): JsonResponse
    {
        $abbreviation = Abbreviation::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating abbreviations',
            'data' => $abbreviation
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowAbbreviationRequest $request
     * @param Abbreviation $abbreviation
     * @return JsonResponse
     */
    public function show(ShowAbbreviationRequest $request, Abbreviation $abbreviation): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching abbreviation',
            'data' => $abbreviation
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateAbbreviationRequest $request
     * @param Abbreviation $abbreviation
     * @return JsonResponse
     */
    public function update(UpdateAbbreviationRequest $request, Abbreviation $abbreviation): JsonResponse
    {
        $abbreviation->update($request->validated());

        return response()->json([
            'message' => 'Success updating abbreviation',
            'data' => $abbreviation
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteAbbreviationRequest $request
     * @param Abbreviation $abbreviation
     * @return JsonResponse
     */
    public function destroy(DeleteAbbreviationRequest $request, Abbreviation $abbreviation): JsonResponse
    {
        $abbreviation->delete();

        return response()->json([
            'message' => 'Success deleting abbreviation',
            'data' => null,
        ], SymfonyResponse::HTTP_OK);
    }
}
