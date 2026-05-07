<?php

namespace App\Http\Controllers;

use App\Http\Requests\Test\DeleteTestRequest;
use App\Http\Requests\Test\ShowTestRequest;
use App\Http\Requests\Test\StoreTestRequest;
use App\Http\Requests\Test\UpdateTestRequest;
use App\Http\Requests\Test\ViewTestRequest;
use App\Models\Test;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewTestRequest $request): JsonResponse
    {
        $tests = Test::query()->get();

        if ($tests->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching tests',
            'data' => $tests
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTestRequest $request
     * @return JsonResponse
     */
    public function store(StoreTestRequest $request): JsonResponse
    {
        $test = Test::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating tests',
            'data' => $test
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowTestRequest $request
     * @param Test $test
     * @return JsonResponse
     */
    public function show(ShowTestRequest $request, Test $test): JsonResponse
    {
        return response()->json([
            'message' => 'Success fetching test',
            'data' => $test
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTestRequest $request
     * @param Test $test
     * @return JsonResponse
     */
    public function update(UpdateTestRequest $request, Test $test): JsonResponse
    {
        $test->update($request->validated());

        return response()->json([
            'message' => 'Success updating test',
            'data' => $test
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteTestRequest $request
     * @param Test $test
     * @return JsonResponse
     */
    public function destroy(DeleteTestRequest $request, Test $test): JsonResponse
    {
        if ($test->loadExists('waterSampleDetails')->water_sample_details_exists) {
            return response()->json([
                'message' => 'Error deleting test, delete all water sample details belonging to this test first',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }
        $test->delete();

        return response()->json([
            'message' => 'Success deleting test',
            'data' => $test
        ], SymfonyResponse::HTTP_OK);
    }
}
