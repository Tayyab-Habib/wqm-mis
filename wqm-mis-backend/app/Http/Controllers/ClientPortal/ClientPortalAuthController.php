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
    /** Tokens expire 30 days after issuance. Re-login refreshes them. */
    private const TOKEN_TTL_DAYS = 30;

    /**
     * Client portal login — email + pre-created password.
     *
     * Issues a 60-char random token to the client and stores only its
     * sha256 hash + expiry on the row, so a DB leak doesn't expose
     * usable tokens. Rate limited at the route level.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // SoftDeletes global scope on Client auto-excludes deactivated clients
        // (deleted_at IS NULL is added implicitly). whereNotNull('password')
        // blocks portal-disabled clients (clients seeded without a password).
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

        // Generate a random plaintext token to return to the client; store
        // only its hash. Mirrors Sanctum's personal_access_tokens design.
        $plaintext = Str::random(60);
        $client->update([
            'portal_token'             => hash('sha256', $plaintext),
            'portal_token_expires_at'  => now()->addDays(self::TOKEN_TTL_DAYS),
        ]);

        return response()->json([
            'message' => 'Login successful',
            'data' => [
                'id'                => $client->id,
                'name'              => $client->name,
                'email'             => $client->email,
                'phone'             => $client->phone,
                'organization_name' => $client->organization_name,
                'token'             => $plaintext,
                'user_type'         => 'client',
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Client portal logout — revoke token.
     *
     * Runs INSIDE the client.portal middleware group now, so we can pull
     * the authenticated client from request attributes (set by the
     * middleware) rather than re-querying with an unhashed token.
     */
    public function logout(Request $request): JsonResponse
    {
        /** @var Client|null $client */
        $client = $request->attributes->get('portal_client');

        if ($client) {
            $client->update([
                'portal_token'             => null,
                'portal_token_expires_at'  => null,
            ]);
        }

        return response()->json(['message' => 'Logged out successfully'], SymfonyResponse::HTTP_OK);
    }
}
