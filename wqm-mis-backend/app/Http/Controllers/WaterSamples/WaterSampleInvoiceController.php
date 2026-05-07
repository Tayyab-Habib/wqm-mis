<?php

namespace App\Http\Controllers\WaterSamples;

use App\Enums\TestTypeEnum;
use App\Enums\WaterSampleInvoiceStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WaterSampleInvoice\IndexWaterSampleInvoiceRequest;
use App\Http\Requests\WaterSampleInvoice\ShowWaterSampleInvoiceRequest;
use App\Http\Requests\WaterSampleInvoice\UpdateWaterSampleInvoiceRequest;
use App\Models\Test;
use Illuminate\Support\Str;
use PDF;
use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class WaterSampleInvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param IndexWaterSampleInvoiceRequest $request
     * @return JsonResponse
     */
    public function index(IndexWaterSampleInvoiceRequest $request): JsonResponse
    {
        $authUser = auth()->user();
        $waterSampleInvoices = WaterSampleInvoice::query()
            ->when(!$authUser->hasRole('system-administrator'), fn($query) => $query->where('created_by', '=', $authUser->id))
            ->has('waterSample')
            ->with([
                'waterSample:id,slug' => [
                    'waterScheme:id,name',
                ],
                'client:id,name',
                'createdByUser:id,name'
            ])
            ->paginate(20);

        if (0 === $waterSampleInvoices->total()) {
            return response()->json([
                'message' => 'No data to show',
                'data' => null,
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Success fetching water samples',
            'data' => $waterSampleInvoices
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowWaterSampleInvoiceRequest $request
     * @param WaterSampleInvoice $waterSampleInvoice
     * @return JsonResponse
     */
    public function show(ShowWaterSampleInvoiceRequest $request, WaterSampleInvoice $waterSampleInvoice)
    {
        $waterSampleInvoice->load([
            'waterSample' => [
                'collectable:id,name,phone,email',
                'laboratory:id,name,address,email,fax,phone,logo',
                'province:id,logo',
                'district:id,name',
                'waterScheme:id,name'
            ],
            'waterSampleInvoiceLogs.user:id,name'
        ]);

        $desiredTests = Test::query()
            ->select('water_quality_parameter')
            ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSampleInvoice->water_sample_id)
                ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
            ->pluck('water_quality_parameter')->toArray();

        $desiredTests = implode(', ', $desiredTests);

        return response()->json([
            'message' => 'Success fetching water sample invoice',
            'data' => [
                'water_sample_invoice' => $waterSampleInvoice,
                'desired_test' => $desiredTests,
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateWaterSampleInvoiceRequest $request
     * @param WaterSampleInvoice $waterSampleInvoice
     * @return JsonResponse
     */
    public function update(UpdateWaterSampleInvoiceRequest $request, WaterSampleInvoice $waterSampleInvoice)
    {
        $validatedData = $request->validated();

        $difference = $waterSampleInvoice->price - ($validatedData['paid'] + $waterSampleInvoice->net_amount ?? 0);

        if ($difference > 0) {
            $status = WaterSampleInvoiceStatusEnum::PARTIAL->value;
        } elseif ($difference < 0) {
            info($difference);
            return response()->json([
                'message' => 'Invalid paid amount',
                'errors' => [
                    'paid' => ['Invalid paid amount']
                ]
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            $status = WaterSampleInvoiceStatusEnum::PAID->value;
        }

        DB::beginTransaction();
        $waterSampleInvoice->waterSampleInvoiceLogs()
            ->create([
                'user_id' => auth()->id(),
                'paid' => $validatedData['paid'],
                'balance' => $difference
            ]);

        $netAmount = $waterSampleInvoice->waterSampleInvoiceLogs()
            ->sum('paid');

        $waterSampleInvoice->update([
            'paid' => $validatedData['paid'],
            'balance' => $difference,
            'status' => $status,
            'net_amount' => $netAmount,
        ]);

        DB::commit();

        if ($waterSampleInvoice->wasChanged()) {
            return response()->json([
                'message' => 'Success updating water sample invoice',
                'data' => $waterSampleInvoice
            ], SymfonyResponse::HTTP_OK);
        }

        return response()->json([
            'message' => 'Error updating water sample invoice'
        ], SymfonyResponse::HTTP_BAD_REQUEST);
    }

    public function generatePdf(WaterSampleInvoice $waterSampleInvoice)
    {
        $waterSampleInvoice->load([
            'waterSample' => [
                'collectable:id,name,phone,email',
                'laboratory:id,name,address,email,fax,phone,logo',
                'province:id,logo',
                'district:id,name',
                'waterScheme:id,name',
            ],
            'waterSampleInvoiceLogs.user:id,name'
        ]);

        $desiredTests = Test::query()
            ->select('water_quality_parameter')
            ->whereHas('waterSampleDetails', fn($query) => $query->where('water_sample_id', '=', $waterSampleInvoice->water_sample_id)
                ->where('type', '=', TestTypeEnum::ON_DEMAND->value))
            ->pluck('water_quality_parameter')->toArray();

        $desiredTests = implode(', ', $desiredTests);

        $pdf = PDF::loadView('waterSample.invoice', compact('waterSampleInvoice', 'desiredTests'));

        $pdf->setOption('page-size', 'A4');

        $fileName = 'water-sample-invoice-' . Str::replace('/', '-', $waterSampleInvoice?->waterSample?->slug) . '-' . now()->format('YmdTHis') . '.pdf';
        return $pdf->download($fileName);
    }
}
