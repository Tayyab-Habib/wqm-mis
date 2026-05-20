<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityLogRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ActivityLogController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  ActivityLogRequest  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'user_id' => ['nullable', 'integer'],
            'days'    => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        $userId = $request->input('user_id');
        // Activity Trail modal footer states "Showing last 30 days by default"
        $sinceDays = (int) $request->input('days', 30);

        $activties = Activity::query()
            ->with([
                'causer:id,name,designation_id' => [
                    'designation:id,name',
                    'laboratoryUser:laboratories.id,name',
                ]
            ])
            // User-scoped lookup: the Activity Trail modal in UsersHR.vue passes
            // ?user_id=N to pull the audit trail for a specific user. Without
            // this filter the modal showed every user's logs (matched by the
            // empty fallback rendering "No activity recorded yet.") because
            // the FE expects rows for THAT user only.
            ->when($userId, fn ($q) => $q
                ->where('causer_id', $userId)
                ->where('causer_type', \App\Models\User::class))
            ->where('created_at', '>=', now()->subDays($sinceDays))
            ->latest()
            ->paginate(50);

        return response()->json([
            'message' => 'Success fetching activity logs',
            'data' => $activties,
        ], SymfonyResponse::HTTP_OK);
    }
}
