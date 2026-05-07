<?php

namespace App\Http\Controllers;

use App\Http\Requests\TermAndCondition\DeleteTermAndConditionRequest;
use App\Http\Requests\TermAndCondition\ShowTermAndConditionRequest;
use App\Http\Requests\TermAndCondition\StoreTermAndConditionRequest;
use App\Http\Requests\TermAndCondition\UpdateTermAndConditionRequest;
use App\Http\Requests\TermAndCondition\ViewTermAndConditionRequest;
use App\Models\TermAndCondition;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TermAndConditionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewTermAndConditionRequest $request)
    {
        $termAndConditions = TermAndCondition::query()
            ->get();

        if (0 === $termAndConditions->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching term and conditions',
            'data' => $termAndConditions
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTermAndConditionRequest $request
     * @return JsonResponse
     */
    public function store(StoreTermAndConditionRequest $request)
    {
        $termAndCondition = TermAndCondition::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating term and conditions',
            'data' => $termAndCondition,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowTermAndConditionRequest $request
     * @param TermAndCondition $termAndCondition
     * @return JsonResponse
     */
    public function show(ShowTermAndConditionRequest $request, TermAndCondition $termAndCondition)
    {
        return response()->json([
            'message' => 'Success fetching term and condition',
            'data' => $termAndCondition,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTermAndConditionRequest $request
     * @param TermAndCondition $termAndCondition
     * @return JsonResponse
     */
    public function update(UpdateTermAndConditionRequest $request, TermAndCondition $termAndCondition)
    {
        $termAndCondition->update($request->validated());

        if ($termAndCondition->wasChanged()) {
            return response()->json([
                'message' => 'Success updating term and conditions',
                'data' => $termAndCondition
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Error updating term and conditions'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteTermAndConditionRequest $request
     * @param TermAndCondition $termAndCondition
     * @return JsonResponse
     */
    public function destroy(DeleteTermAndConditionRequest $request, TermAndCondition $termAndCondition)
    {
        $termAndCondition->delete();

        return response()->json([
            'message' => 'Success deleting term and condition',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
