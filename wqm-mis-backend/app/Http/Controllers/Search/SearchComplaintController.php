<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchComplaintController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchComplaintRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchComplaintRequest $request)
    {
        $authUser = auth()->user();
        $query = Complaint::query()
            ->when(!$authUser->isUnscoped(), fn($query) => $query->where('user_id', '=', $authUser->id))
            ->with('complaintType');

        if (isset($request->complaint_type_id)) {
            $query->where('complaint_type_id', '=', $request->complaint_type_id);
        }

        if (isset($request->status)) {
            $query->where('status', '=', $request->status);
        }

        $complaints = $query->with('complaintType')->paginate(20);

        if (0 === $complaints->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $complaints,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving complaints',
            'data' => $complaints,
        ], SymfonyResponse::HTTP_OK);
    }
}
