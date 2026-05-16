<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Silent dummy mode (SRS §1.2 — "Dummy Account: Full UI access but writes
 * are NOT persisted").
 *
 * For users with users.is_dummy=true, intercept any write request
 * (POST/PUT/PATCH/DELETE) and return a mock success response without
 * letting the controller execute. From the UI's perspective the action
 * succeeded.
 *
 * Read requests (GET/HEAD/OPTIONS) pass through unchanged so the user
 * sees the same real data as an admin would.
 *
 * Register globally in Kernel.php $middleware so it runs before every
 * authenticated request. The auth middleware must run BEFORE this so
 * we have access to the auth user.
 */
class DummyAccountMiddleware
{
    private const WRITE_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * POST endpoints used as "reads with filter params" — dummy users see
     * real data on these. Any POST not in this allowlist is a write and
     * gets silently dropped with a mock success response.
     */
    private const POST_READ_PATTERNS = [
        '#^dashboard(/|$)#',
        '#^district-wise-contaminants$#',
        '#^search-water-sample#',
        '#^reports/#',
        '#^public/results$#',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !($user->is_dummy ?? false)) {
            return $next($request);
        }

        $method = strtoupper($request->method());
        $isWrite = in_array($method, self::WRITE_METHODS, true);

        if ($method === 'POST') {
            $path = ltrim($request->path(), '/');
            if (str_starts_with($path, 'api/')) $path = substr($path, 4);
            $isReadPost = false;
            foreach (self::POST_READ_PATTERNS as $pat) {
                if (preg_match($pat, $path)) { $isReadPost = true; break; }
            }
            $isWrite = !$isReadPost;
        }

        if (!$isWrite) return $next($request);

        // Silent success — UI sees this as a successful write.
        return response()->json([
            'message' => 'Action completed successfully',
            'data'    => null,
        ], SymfonyResponse::HTTP_OK);
    }
}
