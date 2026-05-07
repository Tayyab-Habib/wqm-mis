<?php

namespace App\Http\Controllers;

use App\Http\Requests\Notification\IndexNotificationRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     *  api/notifications             will show only (unread, read) notifications
     *  api/notifications?is_read=1   will show all read notifications
     *
     * @param IndexNotificationRequest $request
     * @return JsonResponse
     */
    public function index(IndexNotificationRequest $request)
    {
        $validatedData = $request->validated();
        $authUser = auth()->user();

        if ($request->has('is_read')) {
            if ($validatedData['is_read'] === 1) {
                $query = $authUser->readNotifications();
            } elseif ($validatedData['is_read'] === 0) {
                $query = $authUser->unreadNotifications();
            }
        } else {
            $query = $authUser->notifications();
        }

        $notifications = $query
            ->with('notifiable:id,name,image')
            ->select([
                'id',
                'data',
                'read_at',
                'created_at',
                'notifiable_type',
                'notifiable_id',
            ])
            ->latest()
            ->get();

        if (0 === $notifications->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $notifications,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching notifications',
            'data' => $notifications
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show($id)
    {
        $notification = auth()->user()
            ->notifications()
            ->with('notifiable:id,name,image')
            ->select([
                'id',
                'data',
                'read_at',
                'created_at',
                'notifiable_type',
                'notifiable_id',
            ])
            ->find($id);

        if (!$notification) {
            return response()->json([
                'message' => 'Error fetching notification',
                'data' => null
            ], SymfonyResponse::HTTP_NOT_FOUND);
        }

        $notification->update(['read_at' => now()]);

        unset($notification['updated_at']);

        return response()->json([
            'message' => 'Success fetching notification',
            'data' => $notification
        ], SymfonyResponse::HTTP_OK);
    }
}
