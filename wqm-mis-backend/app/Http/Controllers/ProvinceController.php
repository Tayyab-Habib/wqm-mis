<?php

namespace App\Http\Controllers;

use App\Http\Requests\Province\DeleteProvinceRequest;
use App\Http\Requests\Province\ShowProvinceRequest;
use App\Http\Requests\Province\StoreProvinceRequest;
use App\Http\Requests\Province\UpdateProvinceRequest;
use App\Http\Requests\Province\ViewProvinceRequest;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProvinceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewProvinceRequest $request)
    {
        $provinces = Province::query()->get();

        if ($provinces->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Success fetching provinces',
            'data' => $provinces
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProvinceRequest $request
     * @return JsonResponse
     */
    public function store(StoreProvinceRequest $request)
    {
        $validatedData = $request->validated();
        $path = 'provinces';
        if (!Storage::disk('public')->path($path)) {
            Storage::disk('public')->makeDirectory($path);
        }

        $logo = Storage::disk('public')->putFile($path, $request->file('logo'));
        $province = Province::query()
            ->create(array_merge($validatedData, ['logo' => $logo]));

        return response()->json([
            'message' => 'Success creating province',
            'data' => $province,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param Province $province
     * @return JsonResponse
     */
    public function show(ShowProvinceRequest $request, Province $province)
    {
        return response()->json([
            'message' => 'Success fetching province',
            'data' => $province
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProvinceRequest $request
     * @param Province $province
     * @return JsonResponse
     */
    public function update(UpdateProvinceRequest $request, Province $province)
    {

        $validatedData = $request->validated();
        $path = 'provinces';
        if (!Storage::disk('public')->path($path)) {
            Storage::disk('public')->makeDirectory($path);
        }
        $logo = Storage::disk('public')->putFile($path, $request->file('logo'));
        $province->update(array_merge($validatedData, ['logo' => $logo]));

        if ($province->wasChanged()) {
            return response()->json([
                'message' => 'Success updating province',
                'data' => $province
            ]);
        }
        return response()->json([
            'message' => 'Error updating province'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Province $province
     * @return JsonResponse
     */
    public function destroy(DeleteProvinceRequest $request, Province $province)
    {
        if ($province->loadExists('divisions')->divisions_exists) {
            return response()->json([
                'message' => 'Error deleting province, delete all divisions belonging to this province first',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }
        $province->delete();

        return response()->json([
            'message' => 'Success deleting province',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
