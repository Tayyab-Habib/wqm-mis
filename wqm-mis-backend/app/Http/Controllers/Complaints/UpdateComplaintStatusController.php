<?php

namespace App\Http\Controllers\Complaints;

use App\Enums\ComplaintStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Complaint\UpdateComplaintStatusRequest;
use App\Models\Complaint;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UpdateComplaintStatusController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param UpdateComplaintStatusRequest $request
     * @param Complaint $complaint
     * @param ComplaintStatusEnum $statusEnum
     * @return JsonResponse
     */
    public function __invoke(UpdateComplaintStatusRequest $request, Complaint $complaint)
    {
        if (($complaint->status->value === ComplaintStatusEnum::CLOSED->value
                && $request->status === ComplaintStatusEnum::IN_PROGRESS->value)
            || (($complaint->status->value === ComplaintStatusEnum::PENDING->value
                    || $complaint->status->value === ComplaintStatusEnum::IN_PROGRESS->value)
                && $request->status === ComplaintStatusEnum::RE_OPENED->value)) {
            return response()->json([
                'message' => 'You do not have permission to update status from ' . $complaint->status->value . ' to ' . $request->status,
                'data' => null,
            ]);
        }

        if ($complaint->status->value === $request->status) {
            return response()->json([
                'message' => 'Complaint already in ' . $complaint->status->value . ' status',
                'data' => null,
            ], SymfonyResponse::HTTP_BAD_REQUEST);
        }

        if (ComplaintStatusEnum::CLOSED->value === $request->status) {
            $complaint->date_of_closing = now()->format('Y-m-d');
        }

        $complaint->status = $request->status;
        $complaint->save();


        // notify user
        switch ($complaint->status->value) {
            case ComplaintStatusEnum::IN_PROGRESS->value:
                $data = [
                    'content' => 'Your complaint ' . $complaint->title . ' is in progress',
                    'status' => ComplaintStatusEnum::IN_PROGRESS->value,
                    'complaint_id' => $complaint->id,
                ];
                auth()->user()->notify(new GenericNotification($data));
                break;
            case ComplaintStatusEnum::CLOSED->value:
                $data = [
                    'content' => 'Your complaint ' . $complaint->title . ' is closed',
                    'status' => ComplaintStatusEnum::CLOSED->value,
                    'complaint_id' => $complaint->id,
                ];
                auth()->user()->notify(new GenericNotification($data));
                break;
            case ComplaintStatusEnum::RE_OPENED->value:
                $data = [
                    'content' => 'Your complaint ' . $complaint->title . ' is re-opened',
                    'status' => ComplaintStatusEnum::RE_OPENED->value,
                    'complaint_id' => $complaint->id,
                ];
                auth()->user()->notify(new GenericNotification($data));
                break;
        }

        return response()->json([
            'message' => 'Success updating complaint status',
            'data' => $complaint
        ], SymfonyResponse::HTTP_OK);
    }
}
