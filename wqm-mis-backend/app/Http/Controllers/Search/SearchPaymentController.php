<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchMaterialRequest;
use App\Http\Requests\Search\SearchPaymentRequest;
use App\Models\Material\Material;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchPaymentController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchPaymentRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchPaymentRequest $request)
    {
        $authUser = auth()->user();
        $validatedData = $request->validated();
        $query = Payment::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('user_id', '=', $authUser->id));

        if (isset($validatedData['starting_amount'], $validatedData['ending_amount'])) {
            $query->whereBetween('amount', [$validatedData['starting_amount'], $validatedData['ending_amount']]);
        }


        $payments = $query->paginate(20);

        if (0 === $payments->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $payments,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving payments',
            'data' => $payments,
        ], SymfonyResponse::HTTP_OK);
    }
}
