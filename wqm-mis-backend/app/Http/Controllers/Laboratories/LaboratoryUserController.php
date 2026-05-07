<?php

namespace App\Http\Controllers\Laboratories;

use App\Http\Controllers\Controller;
use App\Http\Requests\LaboratoryUser\StoreLaboratoryUserRequest;
use App\Http\Resources\LaboratoryUserResource;
use App\Models\Laboratories\Laboratory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class LaboratoryUserController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param StoreLaboratoryUserRequest $request
     * @param Laboratory $laboratory
     * @param User $user
     * @return JsonResponse
     */
    public function store(StoreLaboratoryUserRequest $request, Laboratory $laboratory, User $user)
    {
        $validatedData = $request->validated();

        $laboratory->users()->syncWithoutDetaching([
            $user->id => [
                'present_duty' => $validatedData['present_duty'],
                'assigned_parameters' => $validatedData['assigned_parameters'],
            ]]);

        return response()->json([
            'message' => 'Success adding user to laboratory',
            'data' => new LaboratoryUserResource($laboratory->load('users:id,name,email,phone')),
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Laboratory $laboratory
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(Laboratory $laboratory, User $user)
    {
        $deleteCount = $laboratory->users()->detach([$user->id]);

        if ($deleteCount === 0) {
            return response()->json([
                'message' => 'Error deleting user from laboratory',
                'data' => null,
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            'message' => 'Success deleting laboratory-user',
            'data' => new LaboratoryUserResource($laboratory->load('users:id,name,email,phone')),
        ], SymfonyResponse::HTTP_OK);
    }
}
