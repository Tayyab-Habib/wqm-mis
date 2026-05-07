<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\MarkAsReadNotificationRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MarkAsReadNotificationController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function __invoke(MarkAsReadNotificationRequest $request)
    {
        $validatedData = $request->validated();
        $notificationId = $validatedData['notification_id'];

        $notifications = auth()->user()
            ->notifications()->whereIn('id', $notificationId)->get();

        foreach ($notifications as $notification) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notifications marked as read.',
            'data' => $notifications->toArray()
        ], SymfonyResponse::HTTP_OK);
    }
}
