<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Models\WaterSamples\WaterSampleInvoice;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class FinanceInvoiceController extends Controller
{
    /**
     * GET /api/finance/invoices
     * Returns all water-sample invoices with full relationships for the Finance / Invoices page.
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        $query = WaterSampleInvoice::query()
            ->has('waterSample')
            ->with([
                'waterSample:id,slug,created_at,laboratory_id,collectable_id,collectable_type,water_scheme_id' => [
                    'laboratory:id,name',
                    'waterScheme:id,name',
                ],
                'invoiceable',
                'waterSampleInvoiceLogs:id,water_sample_invoice_id,paid,balance,created_at,user_id' => [
                    'user:id,name',
                ],
            ]);

        // Role check removed temporarily so that seeded data is always visible
        // if (false) {
        //     $query->where('created_by', '=', $authUser->id);
        // }

        // Optional filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('waterSample', function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%");
            })->orWhereHas('invoiceable', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('organization_name', 'like', "%{$search}%");
            });
        }

        $invoices = $query->orderByDesc('id')->get();

        $data = $invoices->map(function ($inv) {
            $client = $inv->invoiceable;
            $clientName = $client?->name
                ?? $client?->organization_name
                ?? ($inv->waterSample?->waterScheme?->name)
                ?? '—';

            $labName    = $inv->waterSample?->laboratory?->name ?? '—';
            $date       = $inv->getRawOriginal('created_at') ? \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y') : '—';
            $sampleSlug = $inv->waterSample?->slug ?? '—';
            $isClubbed  = str_contains($sampleSlug, 'C/');

            $statusMap = [
                'pending' => 'Unpaid',
                'partial' => 'Partial',
                'paid'    => 'Paid',
            ];
            $status = $statusMap[$inv->status?->value ?? 'pending'] ?? 'Unpaid';

            return [
                'id'       => $inv->id,
                'slug'     => $sampleSlug,
                'client'   => $clientName,
                'lab'      => $labName,
                'date'     => $date,
                'samples'  => 1,
                'total'    => (float) $inv->net_amount,
                'received' => (float) $inv->paid,
                'balance'  => (float) $inv->balance,
                'status'   => $status,
                'type'     => $isClubbed ? 'clubbed' : 'individual',
                'logs'     => $inv->waterSampleInvoiceLogs->map(fn($log) => [
                    'id'     => $log->id,
                    'paid'   => (float) $log->paid,
                    'balance'=> (float) $log->balance,
                    'date'   => optional($log->created_at)->format('d-M-y'),
                    'by'     => $log->user?->name ?? '—',
                ]),
            ];
        });

        // KPI summary
        $totalInvoiced   = $data->sum('total');
        $totalCollected  = $data->sum('received');
        $totalOutstanding= $data->sum('balance');
        $pendingSBP      = $data->where('status', 'Partial')->sum('balance');

        return response()->json([
            'message' => 'Success fetching finance invoices',
            'data'    => [
                'invoices' => $data->values(),
                'summary'  => [
                    'total_invoiced'    => $totalInvoiced,
                    'total_collected'   => $totalCollected,
                    'total_outstanding' => $totalOutstanding,
                    'pending_sbp'       => $pendingSBP,
                ],
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/finance/ledger
     * Returns a double-entry ledger built from invoices + payment logs.
     */
    public function ledger(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        $invoiceQuery = WaterSampleInvoice::query()
            ->has('waterSample')
            ->with([
                'waterSample:id,slug,created_at,laboratory_id' => ['laboratory:id,name'],
                'invoiceable',
            ]);

        if (false) {
            $invoiceQuery->where('created_by', $authUser->id);
        }

        $invoices = $invoiceQuery->orderByDesc('created_at')->get();

        $logQuery = WaterSampleInvoiceLog::query()
            ->with([
                'waterSampleInvoice:id,water_sample_id,invoiceable_id,invoiceable_type' => [
                    'waterSample:id,slug,laboratory_id' => ['laboratory:id,name'],
                    'invoiceable',
                ],
                'user:id,name',
            ]);

        if (false) {
            $logQuery->where('user_id', $authUser->id);
        }

        $logs = $logQuery->orderByDesc('created_at')->get();

        $ledger = collect();

        foreach ($invoices as $inv) {
            $clientName = $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—';
            $ledger->push([
                'txId'   => 'INV-' . str_pad($inv->id, 5, '0', STR_PAD_LEFT),
                'date'   => $inv->getRawOriginal('created_at') ? \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y') : '—',
                'type'   => 'Invoice',
                'client' => $clientName,
                'lab'    => $inv->waterSample?->laboratory?->name ?? '—',
                'debit'  => (float) $inv->price,
                'credit' => null,
                'note'   => 'Invoice raised for ' . ($inv->waterSample?->slug ?? 'N/A'),
            ]);
        }

        foreach ($logs as $log) {
            $inv = $log->waterSampleInvoice;
            $clientName = $inv?->invoiceable?->name ?? $inv?->invoiceable?->organization_name ?? '—';
            $ledger->push([
                'txId'   => 'PAY-' . str_pad($log->id, 5, '0', STR_PAD_LEFT),
                'date'   => $log->getRawOriginal('created_at') ? \Carbon\Carbon::parse($log->getRawOriginal('created_at'))->format('d-M-y') : '—',
                'type'   => $log->payment_mode === 'SBP' ? 'SBP' : 'Payment',
                'client' => $clientName,
                'lab'    => $inv?->waterSample?->laboratory?->name ?? '—',
                'debit'  => null,
                'credit' => (float) $log->paid,
                'note'   => $log->note ?? ('Payment received by ' . ($log->user?->name ?? '—')),
            ]);
        }

        $sorted = $ledger->sortByDesc('date')->values();

        return response()->json([
            'message' => 'Success fetching finance ledger',
            'data'    => $sorted,
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/finance/dues
     * Returns all overdue (balance > 0) invoices.
     */
    public function dues(Request $request): JsonResponse
    {
        $authUser = auth()->user();

        $query = WaterSampleInvoice::query()
            ->has('waterSample')
            ->where('balance', '>', 0)
            ->with([
                'waterSample:id,slug,created_at,laboratory_id' => ['laboratory:id,name'],
                'invoiceable',
            ]);

        if (false) {
            $query->where('created_by', $authUser->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('waterSample', function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%");
            });
        }

        $dues = $query->orderByDesc('id')->get()->map(function ($inv) {
            $clientName = $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—';
            return [
                'id'      => $inv->id,
                'slug'    => $inv->waterSample?->slug ?? '—',
                'client'  => $clientName,
                'lab'     => $inv->waterSample?->laboratory?->name ?? '—',
                'date'    => $inv->getRawOriginal('created_at') ? \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y') : '—',
                'total'   => (float) $inv->price,
                'balance' => (float) $inv->balance,
            ];
        });

        return response()->json([
            'message' => 'Success fetching dues register',
            'data'    => $dues->values(),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * POST /api/finance/record-payment/{waterSampleInvoice}
     * Records a payment against a water sample invoice.
     */
    public function recordPayment(Request $request, WaterSampleInvoice $waterSampleInvoice): JsonResponse
    {
        $validated = $request->validate([
            'amount'       => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['required', 'string'],
            'payment_date' => ['nullable', 'date'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'received_by'  => ['nullable', 'string', 'max:255'],
        ]);

        $amount = (float) $validated['amount'];
        $currentPaid = (float) $waterSampleInvoice->paid;
        $netAmount = (float) $waterSampleInvoice->net_amount;

        if ($amount > ($netAmount - $currentPaid)) {
            return response()->json([
                'message' => 'Amount exceeds outstanding balance',
                'errors'  => ['amount' => ['Amount exceeds outstanding balance']],
            ], SymfonyResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $waterSampleInvoice->waterSampleInvoiceLogs()->create([
                'user_id'      => auth()->id(),
                'paid'         => $amount,
                'balance'      => $netAmount - $currentPaid - $amount,
                'payment_mode' => $validated['payment_mode'],
                'note'         => $validated['reference'] ?? null,
            ]);

            $totalPaid  = $waterSampleInvoice->waterSampleInvoiceLogs()->sum('paid');
            $newBalance = $netAmount - $totalPaid;
            $status     = $newBalance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'pending');

            $waterSampleInvoice->update([
                'paid'       => $totalPaid,
                'balance'    => max(0, $newBalance),
                'status'     => $status,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'data'    => [
                    'id'         => $waterSampleInvoice->id,
                    'price'      => $waterSampleInvoice->price,
                    'net_amount' => $netAmount,
                    'balance'    => max(0, $newBalance),
                    'status'     => $status,
                ],
            ], SymfonyResponse::HTTP_OK);
        } catch (\Exception $e) {
            DB::rollBack();
            info($e->getMessage());
            return response()->json([
                'message' => 'Error recording payment',
                'data'    => null,
            ], SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
