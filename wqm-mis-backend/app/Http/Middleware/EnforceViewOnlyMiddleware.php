<?php

namespace App\Http\Middleware;

use App\Services\AuthScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Blocks writes (POST/PUT/PATCH/DELETE) for users flagged as view-only:
 *   - users.is_view_only = true
 *   - role 'view-only-admin' (Director Labs per SRS §1.2)
 *
 * Different from DummyAccountMiddleware (silent success): this returns a
 * clear 403 — view-only users SHOULD see they cannot edit, dummy accounts
 * should NOT.
 */
class EnforceViewOnlyMiddleware
{
    private const WRITE_METHODS = ['PUT', 'PATCH', 'DELETE'];

    /**
     * POST endpoints used as "reads with filter params" — view-only users
     * are allowed to hit these (they don't mutate data). Any POST not in
     * this allowlist is treated as a write and blocked for view-only users.
     *
     * Pattern is checked against the request path (without leading /api).
     */
    private const POST_READ_PATTERNS = [
        '#^dashboard(/|$)#',                       // /dashboard, /dashboard/district-heatmap, /dashboard/lab-kpis
        '#^district-wise-contaminants$#',
        '#^search-water-sample#',                  // search-water-sample, -invoices, -results
        '#^reports/#',                             // /reports/water-quality-analysis, /reports/pwr, /reports/ce-wise, etc.
        '#^public/results$#',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        $method = strtoupper($request->method());

        // PUT/PATCH/DELETE — always block writes for view-only users
        $isWrite = in_array($method, self::WRITE_METHODS, true);

        // POST is ambiguous in this codebase: dashboard, reports, search use POST
        // to send filter params (no mutation). Allow those; block any other POST.
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

        if (!AuthScope::canWrite($user)) {
            return response()->json([
                'message' => 'Your account has view-only access. This action is not permitted.',
                'data'    => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }
        return $next($request);
    }
}
