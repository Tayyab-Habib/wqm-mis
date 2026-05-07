<?php

namespace App\Http\Controllers\Issues;

use App\Enums\IssueStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\StoreIssueLogRequest;
use App\Models\Issues\Issue;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IssueLogController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param StoreIssueLogRequest $request
     * @return JsonResponse
     */
    public function store(StoreIssueLogRequest $request)
    {
        $validatedData = $request->validated();

//        $issueLog = auth()->user()
//            ->issues()
//            ->find($validatedData['issue_id']);

        //TODO: decide which role have permission to add comment
//        if (!$issueLog) {
//            return response()->json([
//                'message' => 'You do not have permission to add comment in this issue',
//                'data' => null
//            ]);
//        }

        if ($request->has('file')) {
            $path = Storage::disk('public')->put('/issues', $request->file);
            $validatedData = array_merge($validatedData, ['file' => $path]);
        }
        try {
            DB::beginTransaction();

            $issue = auth()->user()
                ->issueLogs()
                ->create($validatedData);

            Issue::query()
                ->where('id', '=', $validatedData['issue_id'])
                ->update(['status' => $validatedData['status']]);

            $issueTitle = Issue::query()
                ->select('title')
                ->find($validatedData['issue_id']);

            $issueCreator = Issue::query()
                ->select('user_id')
                ->find($validatedData['issue_id']);


            // notify user
            switch ($validatedData['status']) {
                case IssueStatusEnum::IN_PROGRESS->value:
                    $data = [
                        'content' => 'Your issue with ' . $issueTitle->title . ' is in progress',
                        'status' => IssueStatusEnum::IN_PROGRESS->value,
                        'issue_id' => $validatedData['issue_id'],
                    ];
                    break;
                case IssueStatusEnum::CLOSED->value:
                    $data = [
                        'content' => 'Your issue with ' . $issueTitle->title . ' is closed',
                        'status' => IssueStatusEnum::CLOSED->value,
                        'inventory_detail_id' => $validatedData['issue_id'],
                    ];
                    break;
                case IssueStatusEnum::RE_OPENED->value:
                    $data = [
                        'content' => 'Your issue with ' . $issueTitle->title . ' is re-opened',
                        'status' => IssueStatusEnum::RE_OPENED->value,
                        'inventory_detail_id' => $validatedData['issue_id'],
                    ];
                    break;
            }
            //notify the creator of issue
            $IssueCreator = User::find($issueCreator->user_id);
            $IssueCreator->notify(new GenericNotification($data));

            DB::commit();
        } catch (\Exception $exception) {
            info($exception->getMessage());

            return response()->json([
                'message' => 'Error creating issue log',
                'data' => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json([
            'message' => 'Success creating issue log',
            'data' => $issue
        ], SymfonyResponse::HTTP_CREATED);
    }


}
