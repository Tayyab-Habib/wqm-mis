<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setting\StoreSettingRequest;
use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Http\Requests\Setting\ViewSettingRequest;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SettingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewSettingRequest $request): JsonResponse
    {
        $settings = Setting::query()
            ->get();

        if ($settings->isEmpty()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching settings',
            'data' => $settings
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreSettingRequest $request
     * @return JsonResponse
     */
    public function store(StoreSettingRequest $request): JsonResponse
    {
        $setting = Setting::query()
            ->create($request->validated());

        return response()->json([
            'message' => 'Success creating setting',
            'data' => $setting,
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateSettingRequest $request
     * @param Setting $setting
     * @return JsonResponse
     */
    public function update(UpdateSettingRequest $request, Setting $setting): JsonResponse
    {
        $setting->update($request->validated());

        if ($setting->wasChanged()) {
            return response()->json([
                'message' => 'Success updating setting',
                'data' => $setting
            ],SymfonyResponse::HTTP_OK);
        }
        return response()->json([
            'message' => 'Error updating setting'
        ], SymfonyResponse::HTTP_BAD_REQUEST);

    }

}
