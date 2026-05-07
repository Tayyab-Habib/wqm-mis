<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (
            Auth::attempt([
                'email' => $request->email,
                'password' => $request->password,
            ])
        ) {
            $user = Auth::user();
            $user->token = $user->createToken('MyApp')->plainTextToken;
            $user->load(['roles:id,name']);

            $user->permissions = $user->getAllPermissions()->transform(function ($permission) {
                return $permission->name;
            });

            return response()->json([
                'message' => 'Success login',
                'data' => new UserResource($user),
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Invalid credentials, please try again',
            'errors' => null
        ], SymfonyResponse::HTTP_UNAUTHORIZED);
    }
}
