<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateModifiedByCreatedByFields
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse) $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        try {
            if ($response instanceof Response
                && ($request->method() === 'PUT' || $request->method() === 'DELETE')
                && $response->getStatusCode() === 200) {
                $model = $response->getOriginalContent()['data'];
                if (in_array('modified_by', $model->getFillable())) {
                    $model->update([
                        'modified_by' => auth()->id()
                    ]);
                }
            } elseif ($response instanceof Response
                && $request->method() === 'POST'
                && $response->getStatusCode() === 201) {
                $model = $response->getOriginalContent()['data'];
                if (in_array('created_by', $model->getFillable())) {
                    $model->update([
                        'created_by' => auth()->id()
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            // Widened from \Exception → \Throwable because controllers that
            // return an array (not a Model) under the 'data' key cause
            // getFillable() to throw an \Error (TypeError on member access).
            // Already a no-op fallback — fields are now set at insert time
            // in modern controllers — so swallowing is intentional.
            info($exception->getMessage());
        }
    }
}
