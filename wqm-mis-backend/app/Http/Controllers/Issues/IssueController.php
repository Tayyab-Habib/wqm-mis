<?php

namespace App\Http\Controllers\Issues;

use App\Enums\IssueStatusEnum;
use App\Enums\IssueTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\DeleteIssueRequest;
use App\Http\Requests\Issue\ShowIssueRequest;
use App\Http\Requests\Issue\StoreIssueRequest;
use App\Http\Requests\Issue\UpdateIssueRequest;
use App\Http\Requests\Issue\ViewIssueRequest;
use App\Models\Asset\Asset;
use App\Models\Complaint;
use App\Models\Issues\Issue;
use App\Models\Laboratories\Laboratory;
use App\Models\Material\Material;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(ViewIssueRequest $request)
    {
        $authUser = auth()->user();
        $issues = Issue::query()
            ->with('user:id,name')
            ->when(!$authUser->isUnscoped(), fn($query) => $query->where('user_id', '=', $authUser->id))
            ->paginate(20);

        if (0 === $issues->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching issues',
            'data' => $issues
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreIssueRequest $request
     * @return JsonResponse
     */
    public function store(StoreIssueRequest $request)
    {
        $validatedData = $request->validated();

        switch ($request->issuable_type) {
            case IssueTypeEnum::LABORATORY->value:
                $issuableType = Laboratory::class;
                break;
            case IssueTypeEnum::COMPLAINT->value:
                $issuableType = Complaint::class;
                break;
            case IssueTypeEnum::INVENTORY->value:
                $issuableType = Asset::class;
                break;
            case IssueTypeEnum::STOCK->value:
                $issuableType = Material::class;
                break;
        }

        $validatedData = array_merge($validatedData, ['issuable_type' => $issuableType]);

        if ($request->has('file')) {
            $path = Storage::disk('public')->put('/issues', $request->file);
            $validatedData = array_merge($validatedData, ['file' => $path]);
        }

        $issue = auth()->user()
            ->issues()
            ->create($validatedData);


        $issuableType = ($validatedData['issuable_type'] === Laboratory::class
            ? IssueTypeEnum::LABORATORY->value
            : ($validatedData['issuable_type'] === Material::class
                ? IssueTypeEnum::STOCK->value
                : ($validatedData['issuable_type'] === Asset::class
                    ? IssueTypeEnum::INVENTORY->value
                    : IssueTypeEnum::COMPLAINT->value)));

        // notify system-administrator
        $data = [
            'content' => sprintf('You have an issue with %s', $validatedData['title']),
            'issuable_type' => $issuableType,
            'issuable_id' => $validatedData['issuable_id'],
        ];
        auth()->user()->notify(new GenericNotification($data));


        return response()->json([
            'message' => 'Success creating issue',
            'data' => $issue
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowIssueRequest $request
     * @param Issue $issue
     * @return JsonResponse
     */
    public function show(ShowIssueRequest $request, Issue $issue)
    {
        $user = auth()->user();

        // Check if the user is either the creator of the issue, an issue responsible, or a system administrator
        $isCreator = $user->id === (int)$issue->user_id;
        $isResponsible = $issue->issueResponsibles()->where('responsible_id', $user->id)->exists();
        $isSystemAdmin = $user->isUnscoped();

        if (!$isCreator && !$isResponsible && !$isSystemAdmin) {
            return response()->json([
                'message' => 'You do not have permission to view this Issue',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $issue->load([
            'user:id,name,image',
            'issueLogs.user:id,name,image',
            'issuable',
            'issueResponsibles' => [
                'responsible:id,name'
            ]
        ]);

        $issue->issuable_type = ($issue->issuable_type === Laboratory::class
            ? IssueTypeEnum::LABORATORY->value
            : ($issue->issuable_type === Material::class
                ? IssueTypeEnum::STOCK->value
                : ($issue->issuable_type === Asset::class
                    ? IssueTypeEnum::INVENTORY->value
                    : IssueTypeEnum::COMPLAINT->value)));

        return response()->json([
            'message' => 'Success fetching issue',
            'data' => $issue
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateIssueRequest $request
     * @param Issue $issue
     * @return JsonResponse
     */
    public function update(UpdateIssueRequest $request, Issue $issue)
    {
        if (auth()->id() !== (int)$issue->user_id) {
            return response()->json([
                'message' => 'You do not have permission to view this Issue',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        if ($issue->status->value !== IssueStatusEnum::PENDING->value) {
            return response()->json([
                'message' => 'You are not able to update issue because your issue is in ' . $issue->status->value . ' status',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $validatedData = $request->validated();

        switch ($validatedData['issuable_type']) {
            case IssueTypeEnum::STOCK->value :
                $issuable = Material::class;
                break;
            case IssueTypeEnum::INVENTORY->value :
                $issuable = Asset::class;
                break;
            case IssueTypeEnum::COMPLAINT->value :
                $issuable = Complaint::class;
                break;
            case IssueTypeEnum::LABORATORY->value:
                $issuable = Laboratory::class;
                break;
        }

        $validatedData['issuable_type'] = $issuable;
        $path = 'issues';
        if (!Storage::disk('public')->path($path)) {
            Storage::disk('public')->makeDirectory($path);
        }

        $file = $issue->getAttributes()['file'];

        if ($request->hasFile('file')) {
            $file = Storage::disk('public')->putFile($path, $request->file('file'));
        }
        $issue->update(array_merge($validatedData, ['file' => $file]));

        return response()->json([
            'message' => 'Success updating issue',
            'data' => $issue
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DeleteIssueRequest $request
     * @param Issue $issue
     * @return JsonResponse
     */
    public function destroy(DeleteIssueRequest $request, Issue $issue)
    {
        if (auth()->id() !== (int)$issue->user_id) {
            return response()->json([
                'message' => 'You do not have permission to delete this issue',
                'data' => null,
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        $issue->delete();

        return response()->json([
            'message' => 'Success deleting issue',
            'data' => $issue
        ], SymfonyResponse::HTTP_OK);
    }
}
