<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Client portal auth middleware (hardened 2026-05-18).
 *
 * The plaintext bearer token presented by the client is hashed
 * (sha256 hex) before being compared against the stored hash in
 * `clients.portal_token`. The DB never holds the plaintext token,
 * mirroring how Laravel Sanctum hashes personal_access_tokens.
 *
 * Tokens also carry an expiry — `portal_token_expires_at`. Any
 * token past its expiry is rejected (treated the same as an
 * unknown token to avoid leaking expiry state).
 *
 * Client::where(...) goes through Eloquent so the SoftDeletes
 * global scope automatically excludes soft-deleted clients — a
 * deactivated client's token stops working the moment they're
 * removed.
 */
class AuthenticateClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Hash the incoming plaintext token to look up the stored hash.
        $hash = hash('sha256', $token);

        $client = Client::query()
            ->where('portal_token', $hash)
            ->where(function ($q) {
                // Reject expired tokens (column is nullable; null = no expiry,
                // but we never issue tokens without one so this is defensive).
                $q->whereNull('portal_token_expires_at')
                  ->orWhere('portal_token_expires_at', '>', now());
            })
            ->first();

        if (!$client) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bind the authenticated client to the request so controllers can use it
        $request->merge(['_portal_client' => $client]);
        $request->attributes->set('portal_client', $client);

        return $next($request);
    }
}
