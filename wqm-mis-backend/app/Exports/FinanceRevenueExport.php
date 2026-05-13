<?php

namespace App\Exports;

use App\Models\WaterSamples\WaterSampleInvoice;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * F-18 — Revenue Register exported as a real .xlsx workbook.
 *
 * Replaces the legacy CSV builder in Invoices.vue. Columns match the SRS
 * §2.18.1 Consolidated Revenue Register specification.
 */
class FinanceRevenueExport implements FromCollection, WithHeadings, WithMapping
{
    /** @var \Illuminate\Support\Collection */
    private $cached;

    public function __construct(private array $filters = [])
    {
    }

    public function headings(): array
    {
        return [
            'Invoice ID', 'Client / WSS Name', 'Lab', 'Invoice Date',
            'Amount Invoiced', 'Amount Received', 'Balance Due',
            'Payment Mode', 'Receipt No', 'Date of Payment', 'Recorded By',
            'Status',
        ];
    }

    public function collection()
    {
        if ($this->cached) {
            return $this->cached;
        }

        $q = WaterSampleInvoice::query()->with([
            'waterSample.laboratory:id,name,code',
            'waterSample.waterScheme:id,name',
            'invoiceable',
            'waterSampleInvoiceLogs' => fn ($qq) => $qq->latest('id')->limit(1),
            'waterSampleInvoiceLogs.user:id,name',
            'childInvoices.waterSample.laboratory:id,name,code',
        ]);

        if (!empty($this->filters['lab_id'])) {
            $labId = (int) $this->filters['lab_id'];
            $q->whereHas('waterSample', fn ($qq) => $qq->where('laboratory_id', $labId));
        }
        if (!empty($this->filters['client_id'])) {
            $q->where('invoiceable_id', (int) $this->filters['client_id']);
        }
        if (!empty($this->filters['date_from'])) {
            $q->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $q->whereDate('created_at', '<=', $this->filters['date_to']);
        }
        if (!empty($this->filters['status'])) {
            $q->where('status', $this->filters['status']);
        }

        return $this->cached = $q->orderByDesc('id')->get();
    }

    public function map($inv): array
    {
        $client = $inv->invoiceable;
        $clientName = $client?->name ?? $client?->organization_name ?? $inv->waterSample?->waterScheme?->name ?? '—';
        $labName = $inv->waterSample?->laboratory?->name
                   ?? $inv->childInvoices->first()?->waterSample?->laboratory?->name
                   ?? '—';
        $latest = $inv->waterSampleInvoiceLogs->first();
        $slug   = $inv->is_clubbed ? $inv->clubbed_slug : ($inv->waterSample?->slug ?? 'INV-' . $inv->id);

        return [
            $slug,
            $clientName,
            $labName,
            optional($inv->created_at)->format('Y-m-d'),
            (float) $inv->net_amount,
            (float) $inv->paid,
            (float) $inv->balance,
            $latest?->payment_mode     ?? '—',
            $latest?->receipt_no       ?? $latest?->note ?? '—',
            optional($latest?->payment_date)->format('Y-m-d') ?? \Carbon\Carbon::parse($latest?->getRawOriginal('created_at'))->format('Y-m-d') ?? '—',
            $latest?->received_by_name ?? $latest?->user?->name ?? '—',
            $inv->status_label,
        ];
    }
}
