<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserPasswordController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param UpdateUserPasswordRequest $request
     * @return JsonResponse
     */
    public function __invoke(UpdateUserPasswordRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();

        if (!Hash::check($validatedData['old_password'], $authUser->password)) {
            return response()->json([
                'message' => 'The old password is incorrect',
                'errors' => [
                    'old_password' => [
                        'The old password is incorrect'
                    ]
                ],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validatedData['password'] = bcrypt($validatedData['password']);
        $authUser->update([
            'password' => $validatedData['password'],
        ]);
        return response()->json([
            'message' => 'Success updating user password',
            'data' => null
        ], SymfonyResponse::HTTP_OK);
    }
}
