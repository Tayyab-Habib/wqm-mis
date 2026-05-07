<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchIssueRequest;
use App\Models\Issues\Issue;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchIssueController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchIssueRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchIssueRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $query = Issue::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('user_id', '=', $authUser->id));

        if (isset($validatedData['issuable_type'])) {
            $query->where('issuable_type', '=', $validatedData['issuable_type']);
        }

        if (isset($validatedData['status'])) {
            $query->where('status', '=', $validatedData['status']);
        }

        $issues = $query->paginate(20);

        if (0 === $issues->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $issues,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving issues',
            'data' => $issues,
        ], SymfonyResponse::HTTP_OK);
    }
}
