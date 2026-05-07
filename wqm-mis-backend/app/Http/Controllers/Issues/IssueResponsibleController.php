<?php

namespace App\Http\Controllers\Issues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\DeleteIssueResponsibleRequest;
use App\Http\Requests\Issue\StoreIssueResponsibleRequest;
use App\Http\Requests\Issue\UpdateIssueResponsibleRequest;
use App\Models\Issues\Issue;
use App\Models\Issues\IssueResponsible;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IssueResponsibleController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param StoreIssueResponsibleRequest $request
     * @return JsonResponse
     */
    public function store(StoreIssueResponsibleRequest $request)
    {
        $validatedData = $request->validated();

        foreach ($validatedData['details'] as $index => $_) {
            $issueResponsibleData = [
                'responsible_id' => $validatedData['details'][$index]['responsible_id'],
                'responsible_type' => $validatedData['details'][$index]['responsible_type']
            ];

            $issue = Issue::query()
                ->find($validatedData['issue_id']);

            $issueResponsible = $issue->issueResponsibles()
                ->create($issueResponsibleData);

            // notify (primary and secondary) responsible users
            $data = [
                'content' => sprintf('An issue is assigned to you with ' . $issue->title),
                'issuable_type' => $issue->issuable_type,
                'issuable_id' => $issue->id,
            ];

            User::query()
                ->find($validatedData['details'][$index]['responsible_id'])
                ->notify(new GenericNotification($data));
        }

        return response()->json([
            'message' => 'Success creating issue',
            'data' => $issueResponsible
        ], SymfonyResponse::HTTP_CREATED);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateIssueResponsibleRequest $request
     * @param IssueResponsible $issueResponsible
     * @return JsonResponse
     */
    public function update(UpdateIssueResponsibleRequest $request, IssueResponsible $issueResponsible)
    {
        $validatedData = $request->validated();

        foreach ($validatedData['responsible_type'] as $index => $_) {
            $issueResponsibleData = [
                'responsible_type' => $validatedData['responsible_type'][$index]
            ];

            $issue = $issueResponsible->issue;

            // notify (primary and secondary) responsible users
            $data = [
                'content' => sprintf('An issue is assigned to you with ' . $issue->title),
                'issuable_type' => $issue->issuable_type,
                'issuable_id' => $issue->id,
            ];

            DB::beginTransaction();

            User::query()
                ->find($validatedData['responsible_id'])
                ->notify(new GenericNotification($data));

            $issueResponsible->update($issueResponsibleData);

            DB::commit();
        }
        return response()->json([
            'message' => 'Success updating issue responsible',
            'data' => $issueResponsible
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param IssueResponsible $issueResponsible
     * @return JsonResponse
     */
    public
    function destroy(DeleteIssueResponsibleRequest $request, IssueResponsible $issueResponsible)
    {
        $issue = $issueResponsible->issue;

        // notify (primary and secondary) responsible users
        $data = [
            'content' => sprintf('The issue ' . $issue->title . 'that was assigned to you has been deleted'),
            'issuable_type' => $issue->issuable_type,
            'issuable_id' => $issue->id,
        ];

        $issueResponsible->delete();

        User::query()
            ->find($issueResponsible->id)
            ->notify(new GenericNotification($data));


        return response()->json([
            'message' => 'Success deleting issue responsible',
            'data' => $issueResponsible
        ], SymfonyResponse::HTTP_OK);
    }

}
