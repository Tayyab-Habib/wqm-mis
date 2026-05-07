<?php

namespace App\Http\Controllers\Search;

use App\Enums\WaterSampleInvoiceStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchWaterSampleInvoiceRequest;
use App\Models\Client;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class SearchWaterSampleInvoiceController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param SearchWaterSampleInvoiceRequest $request
     * @return JsonResponse
     */
    public function __invoke(SearchWaterSampleInvoiceRequest $request)
    {
        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();
        $waterSampleInvoiceLogs = WaterSampleInvoiceLog::query()
            ->with([
                'waterSampleInvoice:id,water_sample_id,status,net_amount' => [
                    'waterSample' => function ($query) {
                        $query->select(['id', 'slug', 'created_at']);
                    },
                ]
            ])
            ->when(isset($request->client_id), function ($query) use ($request) {
                $query->whereHas('waterSampleInvoice', function ($query) use ($request) {
                    $query->where('invoiceable_id', '=', $request->client_id)
                        ->where('invoiceable_type', '=', Client::class);
                });
            })
            ->whereDoesntHave('payments')
            ->where('user_id', '=', auth()->id())
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->get();

        if (0 === $waterSampleInvoiceLogs->count()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water sample invoice logs',
            'data' => $waterSampleInvoiceLogs
        ], SymfonyResponse::HTTP_OK);
    }
}
