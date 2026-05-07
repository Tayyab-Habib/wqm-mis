<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\ShowUserProfileRequest;
use App\Http\Requests\User\ShowUserRequest;
use App\Http\Requests\User\UpdateUserProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserProfileController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param UpdateUserProfileRequest $request
     * @return JsonResponse
     */
    public function update(UpdateUserProfileRequest $request): JsonResponse
    {
        $validatedData = $request->validated();

        try {
            $path = 'users';
            if (!Storage::disk('public')->path($path)) {
                Storage::disk('public')->makeDirectory($path);
            }

            $image = auth()->user()->getAttributes()['image'];

            if ($request->hasFile('image')) {
                $image = Storage::disk('public')->putFile($path, $request->file('image'));
            }
            info($validatedData);

            auth()->user()->update(array_merge($validatedData, ['image' => $image]));

            return response()->json([
                'message' => 'Success updating user profile',
                'data' => auth()->user()->load(['laboratories:id,name', 'roles']),
            ], SymfonyResponse::HTTP_OK);
        } catch (\Exception $exception) {
            info($exception);
            return response()->json([
                'message' => 'Error updating user profile',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param ShowUserProfileRequest $request
     * @return JsonResponse
     */
    public function show(ShowUserProfileRequest $request)
    {
        return response()->json([
            'message' => 'Success fetching user profile',
            'data' => auth()->user()->load([
                'designation:id,name',
                'roles:id,name',
                'district:id,name,division_id' => [
                    'division:id,name,province_id' => [
                        'province:id,name'
                    ],
                ],
                'laboratoryUser:laboratories.id,name',
                'laboratoryDetails:id,user_id,assigned_parameters,present_duty,laboratory_id'
            ])
        ], SymfonyResponse::HTTP_OK);
    }
}
