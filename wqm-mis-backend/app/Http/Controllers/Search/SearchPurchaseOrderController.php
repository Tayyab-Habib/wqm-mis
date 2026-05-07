<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchPurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchPurchaseOrderController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchPurchaseOrderRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchPurchaseOrderRequest $request)
    {
        $authUser = auth()->user();
        $query = PurchaseOrder::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('user_id', '=', $authUser->id));


        if (isset($request->status)) {
            $query->where('status', '=', $request->status);
        }

        if (isset($request->starting_date, $request->ending_date)) {
            $query->whereBetween('date_of_order', [$request->starting_date, $request->ending_date]);
        }


        $purchaseOrders = $query->paginate(20);

        if (0 === $purchaseOrders->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => $purchaseOrders,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success retrieving purchase orders',
            'data' => $purchaseOrders,
        ], SymfonyResponse::HTTP_OK);
    }
}
