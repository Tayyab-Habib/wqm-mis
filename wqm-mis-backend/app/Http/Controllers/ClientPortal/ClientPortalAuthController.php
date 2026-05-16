<?php

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ClientPortalAuthController extends Controller
{
    /**
     * Client portal login — email + pre-created password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $client = Client::query()
            ->whereNotNull('email')
            ->where('email', $request->email)
            ->whereNotNull('password')
            ->first();

        if (!$client || !Hash::check($request->password, $client->password)) {
            return response()->json([
                'message' => 'Invalid credentials, please try again.',
            ], SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        // Generate a simple bearer token stored on the client row
        $token = Str::random(60);
        $client->update(['portal_token' => $token]);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'id'                => $client->id,
                'name'              => $client->name,
                'email'             => $client->email,
                'phone'             => $client->phone,
                'organization_name' => $client->organization_name,
                'token'             => $token,
                'user_type'         => 'client',
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Client portal logout — revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token  = $request->bearerToken();
        $client = Client::where('portal_token', $token)->first();

        if ($client) {
            $client->update(['portal_token' => null]);
        }

        return response()->json(['message' => 'Logged out successfully'], SymfonyResponse::HTTP_OK);
    }
}
