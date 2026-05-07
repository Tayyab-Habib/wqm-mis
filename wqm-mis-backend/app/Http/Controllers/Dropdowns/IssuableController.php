<?php

namespace App\Http\Controllers\Dropdowns;

use App\Enums\IssueTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Issue\IssuableRequest;
use App\Models\Asset\Asset;
use App\Models\Complaint;
use App\Models\Laboratories\Laboratory;
use App\Models\Material\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class IssuableController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function __invoke(IssuableRequest $request)
    {
        $authUser = auth()->user();
        switch ($request->issuable_type) {
            case IssueTypeEnum::COMPLAINT->value:
                $query = Complaint::query();
                break;

            case IssueTypeEnum::LABORATORY->value:
                $query = Laboratory::query();
                break;
            case IssueTypeEnum::INVENTORY->value:
                $query = Asset::query();
                break;

            case IssueTypeEnum::STOCK->value:
                $query = Material::query();
                break;
        }

        $issuables = $query->when($request->issuable_type === IssueTypeEnum::COMPLAINT->value, fn ($query) => $query->where('user_id', '=', $authUser->id))
            ->when($request->issuable_type === IssueTypeEnum::LABORATORY->value, fn ($query) => $query->isActive()->where('district_id', '=', $authUser->district_id))
            ->select([
                'id',
                $request->issuable_type === IssueTypeEnum::COMPLAINT->value ? 'title' : 'name',
            ])
            ->get();

        return response()->json([
            'message' => 'Success fetching issuables',
            'data' => $issuables,
        ], SymfonyResponse::HTTP_OK);
    }
}
