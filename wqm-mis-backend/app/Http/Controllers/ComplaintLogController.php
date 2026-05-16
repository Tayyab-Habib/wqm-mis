<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaint\StoreComplaintLogRequest;
use App\Http\Requests\Complaint\UpdateComplaintLogRequest;
use App\Models\Complaint;
use App\Models\ComplaintLog;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ComplaintLogController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreComplaintLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreComplaintLogRequest $request)
    {
        $authUser = auth()->user();
        if ($authUser->isUnscoped()) {
            $complaint = Complaint::find($request->complaint_id);
        } else {
            $complaint = auth()->user()
                ->complaints()
                ->find($request->complaint_id);
        }

        if (!$complaint) {
            return response()->json([
                'message' => 'You do not have permission to add comment in this complaint',
                'data' => null
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }
        $validatedData = $request->validated();

        if ($request->hasFile('image')) {
            $path = Storage::disk('public')->put('/complaints', $request->image);
            $validatedData = array_merge($validatedData, ['file' => $path]);
        }

        $complaintLog = auth()->user()
            ->complaintLogs()
            ->create($validatedData);

        $data = [
            'content' => sprintf('You have a comment on complaint %s', $complaint->title),
            'complaint_id' => $complaint->id,
        ];

        $complaint->user->notify(new GenericNotification($data));

        return response()->json([
            'message' => 'Success creating complaint log',
            'data' => $complaintLog
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ComplaintLog $complaintLog
     * @return JsonResponse
     */
    public function show(ComplaintLog $complaintLog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateComplaintLogRequest $request
     * @param ComplaintLog $complaintLog
     * @return JsonResponse
     */
    public function update(UpdateComplaintLogRequest $request, ComplaintLog $complaintLog)
    {
        if (auth()->id() !== $complaintLog->user_id) {
            return response()->json([
                'message' => 'You do not have permission to view this complaint log',
                'data' => null
            ]);
        }

        $validatedData = $request->validated();
        if ($request->has('image')) {
            $path = Storage::disk('public')->put('/complaints', $request->image);
            collect($validatedData)->merge(['file' => $path]);
        }

        $complaintLog->update($request->validated());

        return response()->json([
            'message' => 'Success updating complaint log',
            'data' => $complaintLog
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ComplaintLog $complaintLog
     * @return JsonResponse
     */
    public function destroy(ComplaintLog $complaintLog)
    {
        //
    }
}
