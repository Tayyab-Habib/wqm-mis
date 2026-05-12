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
     */
    public function index(Request $request): JsonResponse
    {
        $query = WaterSampleInvoice::query()
            ->with([
                'waterSample:id,slug,created_at,laboratory_id,water_scheme_id' => [
                    'laboratory:id,name',
                    'waterScheme:id,name',
                    'waterSampleDetails.test'
                ],
                'invoiceable',
                'childInvoices.waterSample.waterSampleDetails.test',
            ]);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('waterSample', fn($sq) => $sq->where('slug', 'like', "%{$search}%"))
                  ->orWhereHas('invoiceable', fn($iq) => $iq->where('name', 'like', "%{$search}%")->orWhere('organization_name', 'like', "%{$search}%"))
                  ->orWhere('clubbed_slug', 'like', "%{$search}%");
            });
        }
        
        $invoices = $query->orderByDesc('id')->get();

        $data = $invoices->map(function ($inv) {
            $client = $inv->invoiceable;
            $clientName = $client?->name ?? $client?->organization_name ?? ($inv->waterSample?->waterScheme?->name) ?? '—';
            $labName    = $inv->waterSample?->laboratory?->name ?? ($inv->childInvoices->first()?->waterSample?->laboratory?->name) ?? '—';
            $date       = $inv->getRawOriginal('created_at') ? \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y') : '—';
            
            $slug = $inv->is_clubbed ? ($inv->clubbed_slug ?? 'C/'.str_pad($inv->id, 5, '0', STR_PAD_LEFT)) : ($inv->waterSample?->slug ?? '—');
            
            $statusMap = ['pending' => 'Unpaid', 'partial' => 'Partial', 'paid' => 'Paid'];
            $status = $statusMap[$inv->status?->value ?? 'pending'] ?? 'Unpaid';

            return [
                'id'       => $inv->id,
                'slug'     => $slug,
                'client'   => $clientName,
                'lab'      => $labName,
                'date'     => $date,
                'samples'  => $inv->is_clubbed ? $inv->childInvoices->count() : 1,
                'total'    => (float) $inv->net_amount,
                'received' => (float) $inv->paid,
                'balance'  => (float) $inv->balance,
                'status'   => $status,
                'type'     => $inv->is_clubbed ? 'clubbed' : 'individual',
                'is_clubbed' => (bool)$inv->is_clubbed,
                'billing_summary' => $inv->billing_summary, // Added
            ];
        });

        return response()->json([
            'message' => 'Success fetching finance invoices',
            'data'    => [
                'invoices' => $data->values(),
                'summary'  => [
                    'total_invoiced'    => $data->sum('total'),
                    'total_collected'   => $data->sum('received'),
                    'total_outstanding' => $data->sum('balance'),
                    'pending_sbp'       => $data->where('status', 'Partial')->sum('balance'),
                ],
            ],
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/finance/ledger
     */
    public function ledger(Request $request): JsonResponse
    {
        $invoices = WaterSampleInvoice::with(['waterSample.laboratory', 'invoiceable', 'childInvoices.waterSample.laboratory'])->orderByDesc('created_at')->get();
        $logs = WaterSampleInvoiceLog::with([
            'waterSampleInvoice.waterSample.laboratory',
            'waterSampleInvoice.invoiceable',
            'waterSampleInvoice.childInvoices.waterSample.waterSampleDetails.test',
            'user'
        ])->orderBy('created_at', 'desc')->get();

        $ledger = collect();

        foreach ($invoices as $inv) {
            $clientName = $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—';
            $ledger->push([
                'id'           => $inv->id,
                'txId'         => $inv->is_clubbed ? ($inv->clubbed_slug ?? 'C-'.$inv->id) : ($inv->waterSample?->slug ?? 'INV-'.$inv->id),
                'date'         => $inv->getRawOriginal('created_at') ? \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('Y-m-d') : null,
                'type'         => $inv->is_clubbed ? 'Clubbed Invoice' : 'Invoice',
                'client'       => $clientName,
                'lab'          => $inv->waterSample?->laboratory?->name ?? ($inv->childInvoices->first()?->waterSample?->laboratory?->name) ?? '—',
                'amountInvoiced' => (float) $inv->net_amount,
                'amountReceived' => 0,
                'balanceDue'   => (float) $inv->balance,
                'debit'        => (float) $inv->net_amount,
                'credit'       => null,
                'paymentMode'  => '—',
                'receiptNo'    => '—',
                'recordedBy'   => 'System',
                'note'         => $inv->billing_summary['formula'] ?? 'Invoice raised',
            ]);
        }

        foreach ($logs as $log) {
            $inv = $log->waterSampleInvoice;
            if (!$inv) continue;
            $clientName = $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—';
            $ledger->push([
                'id'           => $inv->id,
                'txId'         => 'PAY-' . str_pad($log->id, 5, '0', STR_PAD_LEFT),
                'date'         => $log->getRawOriginal('created_at') ? \Carbon\Carbon::parse($log->getRawOriginal('created_at'))->format('Y-m-d') : null,
                'type'         => $log->payment_mode === 'SBP' ? 'SBP Deposit' : 'Payment',
                'client'       => $clientName,
                'lab'          => $inv->waterSample?->laboratory?->name ?? ($inv->childInvoices->first()?->waterSample?->laboratory?->name) ?? '—',
                'amountInvoiced' => 0,
                'amountReceived' => (float) $log->paid,
                'balanceDue'   => (float) $log->balance,
                'debit'        => null,
                'credit'       => (float) $log->paid,
                'paymentMode'  => $log->payment_mode,
                'receiptNo'    => $log->note ?? '—',
                'recordedBy'   => $log->user?->name ?? '—',
                'note'         => 'Payment received',
            ]);
        }

        return response()->json([
            'message' => 'Success fetching finance ledger',
            'data'    => $ledger->sortByDesc('date')->values(),
        ], SymfonyResponse::HTTP_OK);
    }

    /**
     * GET /api/finance/dues
     */
    public function dues(Request $request): JsonResponse
    {
        $dues = WaterSampleInvoice::where('balance', '>', 0)
            ->with(['waterSample.laboratory', 'invoiceable', 'childInvoices.waterSample.waterSampleDetails.test'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($inv) {
                return [
                    'id'      => $inv->id,
                    'slug'    => $inv->is_clubbed ? $inv->clubbed_slug : ($inv->waterSample?->slug ?? '—'),
                    'client'  => $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—',
                    'lab'     => $inv->waterSample?->laboratory?->name ?? ($inv->childInvoices->first()?->waterSample?->laboratory?->name) ?? '—',
                    'date'    => $inv->getRawOriginal('created_at') ? \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y') : '—',
                    'total'   => (float) $inv->net_amount,
                    'balance' => (float) $inv->balance,
                ];
            });

        return response()->json(['message' => 'Success', 'data' => $dues], SymfonyResponse::HTTP_OK);
    }

    /**
     * POST /api/finance/clubbed-invoice
     */
    public function storeClubbedInvoice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_ids'  => ['required', 'array', 'min:2'],
            'client_id'    => ['required', 'integer'],
            'client_type'  => ['required', 'string'],
            'period_from'  => ['nullable', 'date'],
            'period_to'    => ['nullable', 'date'],
            'description'  => ['nullable', 'string'],
        ]);

        $children = WaterSampleInvoice::whereIn('id', $validated['invoice_ids'])->get();
        $total    = $children->sum('net_amount');

        DB::beginTransaction();
        try {
            // Create Parent Clubbed Invoice
            $clubbed = WaterSampleInvoice::create([
                'invoiceable_id'   => $validated['client_id'],
                'invoiceable_type' => $validated['client_type'],
                'price'            => $total,
                'net_amount'       => $total,
                'paid'             => 0,
                'balance'          => $total,
                'status'           => 'pending',
                'is_clubbed'       => true,
                'period_from'      => $validated['period_from'],
                'period_to'        => $validated['period_to'],
                'created_by'       => auth()->id(),
            ]);

            // Assign unique clubbed slug
            $year = date('y');
            $labCode = 'LAB'; 
            $clubbed->update(['clubbed_slug' => "C/{$year}/{$labCode}/C" . str_pad($clubbed->id, 4, '0', STR_PAD_LEFT)]);

            // Link children
            WaterSampleInvoice::whereIn('id', $validated['invoice_ids'])->update([
                'clubbed_invoice_id' => $clubbed->id
            ]);

            DB::commit();
            return response()->json(['message' => 'Clubbed Invoice generated successfully', 'data' => $clubbed], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/finance/record-payment/{waterSampleInvoice}
     */
    public function recordPayment(Request $request, WaterSampleInvoice $waterSampleInvoice): JsonResponse
    {
        $validated = $request->validate([
            'amount'       => ['required', 'numeric', 'gt:0'],
            'payment_mode' => ['required', 'string'],
            'reference'    => ['nullable', 'string'],
        ]);

        $amount = (float) $validated['amount'];
        if ($amount > $waterSampleInvoice->balance + 0.01) { 
            return response()->json(['message' => 'Exceeds balance'], 422);
        }

        DB::beginTransaction();
        try {
            // Create main log
            $waterSampleInvoice->waterSampleInvoiceLogs()->create([
                'user_id'      => auth()->id(),
                'paid'         => $amount,
                'balance'      => max(0, $waterSampleInvoice->balance - $amount),
                'payment_mode' => $validated['payment_mode'],
                'note'         => $validated['reference'],
            ]);

            // Update parent totals
            $newPaid = $waterSampleInvoice->paid + $amount;
            $newBal  = max(0, $waterSampleInvoice->net_amount - $newPaid);
            $status  = $newBal <= 0 ? 'paid' : 'partial';

            $waterSampleInvoice->update([
                'paid'    => $newPaid,
                'balance' => $newBal,
                'status'  => $status
            ]);

            // PROPORTIONAL DISTRIBUTION for clubbed invoices
            if ($waterSampleInvoice->is_clubbed) {
                $children = $waterSampleInvoice->childInvoices;
                foreach ($children as $child) {
                    $ratio = $child->net_amount / $waterSampleInvoice->net_amount;
                    $childPaid = $amount * $ratio;
                    
                    $child->waterSampleInvoiceLogs()->create([
                        'user_id'      => auth()->id(),
                        'paid'         => $childPaid,
                        'balance'      => max(0, $child->balance - $childPaid),
                        'payment_mode' => $validated['payment_mode'],
                        'note'         => "Distributed from Clubbed Invoice " . $waterSampleInvoice->clubbed_slug,
                    ]);

                    $cPaid = $child->paid + $childPaid;
                    $cBal  = max(0, $child->net_amount - $cPaid);
                    $child->update([
                        'paid'    => $cPaid,
                        'balance' => $cBal,
                        'status'  => $cBal <= 0 ? 'paid' : 'partial'
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Payment recorded successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
