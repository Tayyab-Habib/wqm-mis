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
        $activties = Activity::query()
            ->with([
                'causer:id,name,designation_id' => [
                    'designation:id,name',
                    'laboratoryUser:laboratories.id,name',
                ]
            ])
            ->latest()
            ->paginate('20');

        return response()->json([
            'message' => 'Success fetching activity logs',
            'data' => $activties,
        ], SymfonyResponse::HTTP_OK);
    }
}
