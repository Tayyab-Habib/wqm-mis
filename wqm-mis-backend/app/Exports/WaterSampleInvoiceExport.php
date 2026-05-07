<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WaterSampleInvoiceExport implements FromCollection, withHeadings
{
    protected $invoices;

    public function __construct($invoices)
    {
        $this->invoices = $invoices;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->invoices->transform(function ($invoice) {
            $invoice->water_sample_id = $invoice->waterSample?->slug ?? '';
            $invoice->invoiceable_id = $invoice->invoiceable?->name ?? '';
            $invoice->created_by = $invoice->createdByUser?->name ?? '';

            unset($invoice->id);
            unset($invoice->invoiceable_type);
            return $invoice;
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Water Sample',
            'Invoiceable',
            'Price',
            'Paid',
            'Balance',
            'Status',
            'Created By',
        ];
    }
}
