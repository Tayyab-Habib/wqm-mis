<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ActivityLogController extends Controller
{
    /**
     * Activity log feed for the UsersHR "Activity Trail" modal.
     *
     * Filters:
     *   user_id  — return only entries where this user is the *causer*
     *              (i.e. things they did). Without it the controller returns
     *              the global recent activity, which is the legacy behaviour.
     *
     * Always returns a flat list (not a paginator) so the frontend can iterate
     * directly. Capped at 200 rows to keep the modal responsive.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $limit  = (int) ($request->query('limit') ?? 200);
        $limit  = max(1, min(500, $limit));

        $activities = Activity::query()
            ->with(['causer:id,name,designation_id,email', 'causer.designation:id,name'])
            ->when($userId, function ($q) use ($userId) {
                // Spatie's Activity stores the causer as a polymorphic FK
                // (causer_type, causer_id). Filter to actions performed *by*
                // this user. We intentionally accept either the full FQCN or
                // a relaxed match on the trailing class name in case different
                // parts of the codebase used different morph maps.
                $q->where('causer_id', $userId)
                  ->where(function ($q2) {
                      $q2->where('causer_type', User::class)
                         ->orWhere('causer_type', 'like', '%\\User');
                  });
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($a) {
                // Spatie stores `properties` as a Collection; flatten it for
                // the frontend, and lift the ip address out so the modal can
                // display it in its own column without parsing JSON.
                $props = $a->properties instanceof \Illuminate\Support\Collection
                    ? $a->properties->toArray()
                    : (is_array($a->properties) ? $a->properties : []);
                $subjectShort = $a->subject_type ? class_basename($a->subject_type) : null;

                return [
                    'id'           => $a->id,
                    'log_name'     => $a->log_name ?: 'default',
                    'description'  => $a->description,
                    'event'        => $a->event ?: ucfirst((string) $a->description) ?: '—',
                    'subject_type' => $subjectShort,
                    'subject_id'   => $a->subject_id,
                    'causer_id'    => $a->causer_id,
                    'causer_name'  => $a->causer?->name,
                    'properties'   => $props,
                    'ip'           => $props['ip'] ?? $props['ip_address'] ?? null,
                    'created_at'   => $a->created_at?->toDateTimeString(),
                ];
            });

        return response()->json([
            'message' => 'Success fetching activity logs',
            'data'    => $activities,
        ], SymfonyResponse::HTTP_OK);
    }
}
