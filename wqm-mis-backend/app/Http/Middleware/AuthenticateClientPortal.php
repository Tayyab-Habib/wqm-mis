<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $token  = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $client = Client::where('portal_token', $token)->first();

        if (!$client) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Bind the authenticated client to the request so controllers can use it
        $request->merge(['_portal_client' => $client]);
        $request->attributes->set('portal_client', $client);

        return $next($request);
    }
}
