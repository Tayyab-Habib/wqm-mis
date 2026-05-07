<?php

namespace App\Http\Controllers\Issues;

use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\AssignIssueRequest;
use App\Models\Issues\Issue;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AssignIssueController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param AssignIssueRequest $request
     * @return JsonResponse
     */
    public function __invoke(AssignIssueRequest $request)
    {
        $assignedIssues = auth()->user()
            ->responsibleIssues()
            ->with('user:id,name')
            ->get();

        if (0 === $assignedIssues->count()){
            return response()->json([
                'message' => 'No data to show.',
                'data' => $assignedIssues
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching assigned issues',
            'data' => $assignedIssues
        ], SymfonyResponse::HTTP_OK);
    }
}
