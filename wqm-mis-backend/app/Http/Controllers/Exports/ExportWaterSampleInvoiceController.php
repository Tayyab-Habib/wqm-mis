<?php

namespace App\Http\Controllers\Exports;

use App\Exports\WaterSampleInvoiceExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Export\ExportWaterSampleInvoiceRequest;
use App\Models\WaterSamples\WaterSampleInvoice;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportWaterSampleInvoiceController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param ExportWaterSampleInvoiceRequest $request
     * @return BinaryFileResponse
     */
    public function __invoke(ExportWaterSampleInvoiceRequest $request)
    {
        $query = WaterSampleInvoice::query()->select([
            'water_sample_id',
            'invoiceable_id',
            'invoiceable_type',
            'price',
            'net_amount AS Paid',
            'balance',
            'status',
            'created_by',
        ]);

        $validatedData = $request->validated();

        if (isset($validatedData['laboratory_id'])) {
            $query->whereHas('waterSample', function ($query) use ($validatedData) {
                $query->where('laboratory_id', $validatedData['laboratory_id']);
            });
        }

        if (isset($validatedData['district_id'])) {
            $query->whereHas('waterSample', function ($query) use ($validatedData) {
                $query->where('district_id', $validatedData['district_id']);
            });
        }

        if (isset($validatedData['tehsil_id'])) {
            $query->whereHas('waterSample', function ($query) use ($validatedData) {
                $query->where('tehsil_id', $validatedData['tehsil_id']);
            });
        }

        if (isset($validatedData['status'])) {
            $query->where('status', '=', $validatedData['status']);
        }

        if (isset($validatedData['min_price'], $validatedData['max_price'])) {
            $query->whereBetween('price', [$validatedData['min_price'], $validatedData['max_price']]);
        }

        $waterSampleInvoices = $query->get();

        return Excel::download(new WaterSampleInvoiceExport($waterSampleInvoices), 'water_sample_invoice.csv');
    }
}
