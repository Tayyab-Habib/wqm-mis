<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintStatusEnum;
use App\Http\Requests\Complaint\DeleteComplaintRequest;
use App\Http\Requests\Complaint\ShowComplaintRequest;
use App\Http\Requests\Complaint\StoreComplaintRequest;
use App\Http\Requests\Complaint\UpdateComplaintRequest;
use App\Http\Requests\Complaint\ViewComplaintRequest;
use App\Models\Complaint;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ComplaintController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewComplaintRequest $request)
    {
        $authUser = auth()->user();
        $complaints = Complaint::query()
            ->when(!$authUser->isUnscoped(), fn($query) => $query->where('user_id', '=', $authUser->id))
            ->with(['complaintType', 'user:id,name'])
            ->paginate(20);


        if (0 === $complaints->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching complaints',
            'data' => $complaints
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreComplaintRequest $request
     * @return JsonResponse
     */
    public function store(StoreComplaintRequest $request)
    {
        $validatedData = $request->validated();

        if ($request->has('image')) {
            $path = Storage::disk('public')->put('/complaints', $request->image);
            $validatedData = array_merge($validatedData, ['file' => $path]);
        }

        $complaint = auth()->user()
            ->complaints()
            ->create($validatedData);

        // notify system-administrator
        $data = [
            'content' => sprintf('You have a complaint with %s', $validatedData['title']),
            'status' => ComplaintStatusEnum::PENDING->value,
            'complaint_id' => $complaint->id,
        ];
        auth()->user()->notify(new GenericNotification($data));

        return response()->json([
            'message' => 'Success creating complaints',
            'data' => $complaint
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowComplaintRequest $request
     * @param Complaint $complaint
     * @return JsonResponse
     */
    public function show(ShowComplaintRequest $request, Complaint $complaint)
    {
        if (auth()->id() !== (int)$complaint->user_id && !auth()->user()->isUnscoped()) {
            return response()->json([
                'message' => 'You do not have permission to view this complaint',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        return response()->json([
            'message' => 'Success fetching complaint',
            'data' => $complaint->load(['user:id,name,image', 'complaintType', 'complaintLogs' => fn($query) => $query->with('user:id,name,image')->latest()])
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateComplaintRequest $request
     * @param Complaint $complaint
     * @return JsonResponse
     */
    public function update(UpdateComplaintRequest $request, Complaint $complaint)
    {
        if (auth()->id() !== (int)$complaint->user_id) {
            return response()->json([
                'message' => 'You do not have permission to update this complaint',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        if (ComplaintStatusEnum::PENDING->value !== $complaint->status->value) {
            return response()->json([
                'message' => 'your complaint already in ' . $complaint->status->value,
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validated();

        if ($request->has('image')) {
            $path = Storage::disk('public')->put('/complaints', $request->image);
            $validatedData = array_merge($validatedData, ['file' => $path]);
        }

        $complaint->update($validatedData);

        return response()->json([
            'message' => 'Success updating complaint',
            'data' => $complaint
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteComplaintRequest $request
     * @param Complaint $complaint
     * @return JsonResponse
     */
    public function destroy(DeleteComplaintRequest $request, Complaint $complaint)
    {
        if (auth()->id() !== (int)$complaint->user_id) {
            return response()->json([
                'message' => 'You do not have permission to delete this complaint',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $complaint->delete();

        return response()->json([
            'message' => 'Success deleting complaint',
            'data' => $complaint
        ], SymfonyResponse::HTTP_OK);
    }
}
