<?php

namespace App\Http\Controllers\Finance;

use App\Enums\WaterSampleInvoiceStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\RecordPaymentRequest;
use App\Http\Requests\Finance\StoreClubbedInvoiceRequest;
use App\Models\Client;
use App\Models\WaterSamples\WaterSampleInvoice;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use App\Notifications\ClubbedInvoiceGenerated;
use App\Services\AuthScope;
use App\Services\FinanceSlugService;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as Http;

class FinanceInvoiceController extends Controller
{
    public function __construct(
        private readonly FinanceSlugService $slugService,
        private readonly SmsService $smsService,
    ) {
    }

    /* =========================================================================
     |  F-01  GET /api/finance/invoices
     |        Consolidated Revenue Register.
     |        Each row carries the LATEST payment metadata (mode, receipt no,
     |        date, recorded-by) — not just totals.
     * ===================================================================== */
    public function index(Request $request): JsonResponse
    {
        $query = WaterSampleInvoice::query()
            ->with([
                'waterSample:id,slug,laboratory_id,water_scheme_id,created_at' => [
                    'laboratory:id,name,code',
                    'waterScheme:id,name',
                    'waterSampleDetails.test',
                ],
                'invoiceable',
                'childInvoices.waterSample.laboratory:id,name,code',
                'childInvoices.waterSample.waterSampleDetails.test',
                // F-01 — pull the most recent payment log for each invoice
                'waterSampleInvoiceLogs' => fn ($q) => $q->latest('id')->limit(1),
                'waterSampleInvoiceLogs.user:id,name',
            ]);

        // RBAC: scope invoices through the underlying water_sample's lab.
        // Unscoped roles see everything; scoped roles see only invoices
        // whose sample landed at one of their visible labs.
        AuthScope::waterSampleInvoices($query, auth()->user());

        if ($request->filled('status')) {
            // Accept either the SRS label ("Partially Paid") or the enum value ("partial")
            $key = $this->normalizeStatusFilter($request->status);
            if ($key !== null) {
                $query->where('status', $key);
            }
        }
        if ($request->filled('lab_id')) {
            $labId = (int) $request->lab_id;
            $query->whereHas('waterSample', fn ($q) => $q->where('laboratory_id', $labId));
        }
        if ($request->filled('client_id')) {
            $query->where('invoiceable_id', (int) $request->client_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->whereHas('waterSample', fn ($sq) => $sq->where('slug', 'like', "%{$s}%"))
                    ->orWhereHas('invoiceable', fn ($iq) => $iq->where('name', 'like', "%{$s}%")
                        ->orWhere('organization_name', 'like', "%{$s}%"))
                    ->orWhere('clubbed_slug', 'like', "%{$s}%");
            });
        }

        $invoices = $query->orderByDesc('id')->get();

        $rows = $invoices->map(function (WaterSampleInvoice $inv) {
            $client     = $inv->invoiceable;
            $clientName = $client?->name ?? $client?->organization_name ?? $inv->waterSample?->waterScheme?->name ?? '—';
            $labName    = $inv->waterSample?->laboratory?->name
                         ?? $inv->childInvoices->first()?->waterSample?->laboratory?->name
                         ?? '—';

            $latestLog = $inv->waterSampleInvoiceLogs->first(); // latest()->limit(1) loaded
            $slug      = $inv->is_clubbed
                ? ($inv->clubbed_slug ?: 'C-' . str_pad((string) $inv->id, 5, '0', STR_PAD_LEFT))
                : ($inv->waterSample?->slug ?? '—');

            return [
                'id'                 => $inv->id,
                'slug'               => $slug,
                'client'             => $clientName,
                'lab'                => $labName,
                'date'               => \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y'),
                'samples'            => $inv->is_clubbed ? $inv->childInvoices->count() : 1,
                'total'              => (float) $inv->net_amount,
                'received'           => (float) $inv->paid,
                'balance'            => (float) $inv->balance,
                'status'             => $inv->status_label,                // SRS label (F-02)
                'status_key'         => $inv->status?->value ?? 'pending', // stable key for clients
                'type'               => $inv->is_clubbed ? 'clubbed' : 'individual',
                'is_clubbed'         => (bool) $inv->is_clubbed,
                'billing_summary'    => $inv->billing_summary,

                // F-01 — latest-payment metadata aggregated onto the invoice row
                'payment_mode'       => $latestLog?->payment_mode,
                'receipt_no'         => $latestLog?->receipt_no ?? $latestLog?->note,
                'date_of_payment'    => optional($latestLog?->payment_date)->format('Y-m-d')
                                        ?? \Carbon\Carbon::parse($latestLog?->getRawOriginal('created_at'))->format('Y-m-d'),
                'recorded_by'        => $latestLog?->received_by_name ?? $latestLog?->user?->name,
            ];
        });

        return response()->json([
            'message' => 'Success fetching finance invoices',
            'data'    => [
                'invoices' => $rows->values(),
                'summary'  => $this->buildLightSummary($rows),
            ],
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  GET /api/finance/ledger  — unchanged shape but now per-row friendly.
     * ===================================================================== */
    public function ledger(Request $request): JsonResponse
    {
        $user = auth()->user();

        $invoicesQ = WaterSampleInvoice::with([
            'waterSample.laboratory:id,name,code',
            'invoiceable',
            'childInvoices.waterSample.laboratory:id,name,code',
        ])->orderByDesc('created_at');
        AuthScope::waterSampleInvoices($invoicesQ, $user);
        $invoices = $invoicesQ->get();

        $logsQ = WaterSampleInvoiceLog::with([
            'waterSampleInvoice.waterSample.laboratory:id,name,code',
            'waterSampleInvoice.invoiceable',
            'waterSampleInvoice.childInvoices.waterSample.laboratory:id,name,code',
            'user:id,name',
        ])->orderByDesc('created_at');
        AuthScope::waterSampleInvoiceLogs($logsQ, $user);
        $logs = $logsQ->get();

        $rows = collect();

        foreach ($invoices as $inv) {
            $clientName = $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—';
            $rows->push([
                'id'             => $inv->id,
                'txId'           => $inv->is_clubbed
                                    ? ($inv->clubbed_slug ?: 'C-' . $inv->id)
                                    : ($inv->waterSample?->slug ?? 'INV-' . $inv->id),
                'date'           => \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('Y-m-d'),
                'type'           => $inv->is_clubbed ? 'Clubbed Invoice' : 'Invoice',
                'client'         => $clientName,
                'lab'            => $inv->waterSample?->laboratory?->name
                                    ?? $inv->childInvoices->first()?->waterSample?->laboratory?->name
                                    ?? '—',
                'amountInvoiced' => (float) $inv->net_amount,
                'amountReceived' => 0,
                'balanceDue'     => (float) $inv->balance,
                'debit'          => (float) $inv->net_amount,
                'credit'         => null,
                'paymentMode'    => '—',
                'receiptNo'      => '—',
                'recordedBy'     => 'System',
                'note'           => $inv->billing_summary['formula'] ?? 'Invoice raised',
            ]);
        }

        foreach ($logs as $log) {
            $inv = $log->waterSampleInvoice;
            if (!$inv) {
                continue;
            }
            $rows->push([
                'id'             => $inv->id,
                'txId'           => 'PAY-' . str_pad((string) $log->id, 5, '0', STR_PAD_LEFT),
                'date'           => optional($log->payment_date)->format('Y-m-d') ?? \Carbon\Carbon::parse($log->getRawOriginal('created_at'))->format('Y-m-d'),
                'type'           => $log->sbp_submission_id ? 'SBP Deposit' : 'Payment',
                'client'         => $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—',
                'lab'            => $inv->waterSample?->laboratory?->name
                                    ?? $inv->childInvoices->first()?->waterSample?->laboratory?->name
                                    ?? '—',
                'amountInvoiced' => 0,
                'amountReceived' => (float) $log->paid,
                'balanceDue'     => (float) $log->balance,
                'debit'          => null,
                'credit'         => (float) $log->paid,
                'paymentMode'    => $log->payment_mode ?? '—',
                'receiptNo'      => $log->receipt_no ?? $log->note ?? '—',
                'recordedBy'     => $log->received_by_name ?? $log->user?->name ?? '—',
                'note'           => 'Payment received',
            ]);
        }

        return response()->json([
            'message' => 'Success fetching finance ledger',
            'data'    => $rows->sortByDesc('date')->values(),
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  GET /api/finance/dues  — invoices with outstanding balance.
     * ===================================================================== */
    public function dues(Request $request): JsonResponse
    {
        $duesQ = WaterSampleInvoice::where('balance', '>', 0)
            ->with([
                'waterSample.laboratory:id,name,code',
                'invoiceable',
                'childInvoices.waterSample.laboratory:id,name,code',
            ])
            ->orderByDesc('id');
        AuthScope::waterSampleInvoices($duesQ, auth()->user());
        $dues = $duesQ->get()
            ->map(function (WaterSampleInvoice $inv) {
                return [
                    'id'      => $inv->id,
                    'slug'    => $inv->is_clubbed ? $inv->clubbed_slug : ($inv->waterSample?->slug ?? '—'),
                    'client'  => $inv->invoiceable?->name ?? $inv->invoiceable?->organization_name ?? '—',
                    'lab'     => $inv->waterSample?->laboratory?->name
                                 ?? $inv->childInvoices->first()?->waterSample?->laboratory?->name
                                 ?? '—',
                    'date'    => \Carbon\Carbon::parse($inv->getRawOriginal('created_at'))->format('d-M-y'),
                    'total'   => (float) $inv->net_amount,
                    'balance' => (float) $inv->balance,
                    'status'  => $inv->status_label,
                ];
            });

        return response()->json([
            'message' => 'Success',
            'data'    => $dues,
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  F-05 / F-18  GET /api/finance/revenue-summary
     |               Real revenue figures bucketed by collected / outstanding /
     |               submitted-to-SBP / pending-SBP, with full filter support.
     * ===================================================================== */
    public function revenueSummary(Request $request): JsonResponse
    {
        [$invoiceQuery, $logQuery] = $this->buildFilteredQueries($request);

        $totalInvoiced  = (clone $invoiceQuery)->sum('net_amount');
        $totalCollected = (clone $logQuery)->sum('paid');
        $totalOutstand  = (clone $invoiceQuery)->sum('balance');
        $submittedSbp   = (clone $logQuery)->whereNotNull('sbp_submission_id')->sum('paid');
        $pendingSbp     = (clone $logQuery)->whereNull('sbp_submission_id')->sum('paid');

        return response()->json([
            'message' => 'Success fetching revenue summary',
            'data' => [
                'total_invoiced'    => (float) $totalInvoiced,
                'total_collected'   => (float) $totalCollected,
                'total_outstanding' => (float) $totalOutstand,
                'submitted_to_sbp'  => (float) $submittedSbp,
                'pending_sbp'       => (float) $pendingSbp,
                'filters_applied'   => array_filter([
                    'lab_id'         => $request->lab_id,
                    'client_id'      => $request->client_id,
                    'district_id'    => $request->district_id,
                    'date_from'      => $request->date_from,
                    'date_to'        => $request->date_to,
                    'payment_status' => $request->status,
                ], static fn ($v) => $v !== null && $v !== ''),
            ],
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  F-07  GET /api/finance/dashboard-card
     |        Powers the Total Revenue + Pending Revenue cards.
     |        IMPORTANT: pulls from water_sample_invoice_logs, not from the
     |        legacy `payments` table (purchase orders) which is what the
     |        old `laboratory_wise_revenue` figure was incorrectly using.
     * ===================================================================== */
    public function dashboardCard(Request $request): JsonResponse
    {
        $from = $request->filled('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->startOfMonth();
        $to   = $request->filled('date_to')   ? Carbon::parse($request->date_to)   : Carbon::now()->endOfDay();
        $user = auth()->user();

        $totalRevenueQ  = WaterSampleInvoiceLog::query();
        AuthScope::waterSampleInvoiceLogs($totalRevenueQ, $user);
        $totalRevenue   = (float) $totalRevenueQ->sum('paid');                       // F-07 — all-time total

        $periodRevenueQ = WaterSampleInvoiceLog::whereBetween('created_at', [$from, $to]);
        AuthScope::waterSampleInvoiceLogs($periodRevenueQ, $user);
        $periodRevenue  = (float) $periodRevenueQ->sum('paid');

        // F-06 — Pending is sum of ALL open balances regardless of period
        $pendingRevenueQ = WaterSampleInvoice::where('balance', '>', 0);
        AuthScope::waterSampleInvoices($pendingRevenueQ, $user);
        $pendingRevenue  = (float) $pendingRevenueQ->sum('balance');

        return response()->json([
            'message' => 'Success',
            'data' => [
                'total_revenue'    => $totalRevenue,
                'period_revenue'   => $periodRevenue,
                'pending_revenue'  => $pendingRevenue,
                'period_from'      => $from->toDateString(),
                'period_to'        => $to->toDateString(),
            ],
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  F-08  GET /api/finance/unbilled-by-client/{client_id}
     |        Lists every NOT-yet-clubbed individual invoice for a client.
     |        Drives Step 3 of the SRS wizard.
     * ===================================================================== */
    public function unbilledByClient(int $clientId, Request $request): JsonResponse
    {
        $type = $request->input('client_type', Client::class);
        $from = $request->date_from;
        $to   = $request->date_to;

        $rowsQ = WaterSampleInvoice::query()
            ->where('invoiceable_id', $clientId)
            ->where('invoiceable_type', $type)
            ->where('is_clubbed', false)
            ->whereNull('clubbed_invoice_id')
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to,   fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->with([
                'waterSample:id,slug,laboratory_id,created_at',
                'waterSample.laboratory:id,name,code',
                'waterSample.waterSampleDetails.test',
            ])
            ->orderBy('id');
        AuthScope::waterSampleInvoices($rowsQ, auth()->user());
        $rows = $rowsQ->get();

        $data = $rows->map(fn (WaterSampleInvoice $inv) => [
            'id'         => $inv->id,
            'slug'       => $inv->waterSample?->slug ?? ('INV-' . $inv->id),
            'date'       => \Carbon\Carbon::parse($inv->waterSample?->getRawOriginal('created_at'))->format('d-M-y'),
            'category'   => $inv->calculateCategory(),
            'amount'     => (float) $inv->net_amount,
            'balance'    => (float) $inv->balance,
            'status'     => $inv->status_label,
            'lab_id'     => $inv->waterSample?->laboratory_id,
            'lab_code'   => $inv->waterSample?->laboratory?->code,
            'selected'   => true, // SRS Step 3: pre-selected by default
        ]);

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
            'meta'    => [
                'count' => $data->count(),
                // SRS F-08: client list is filtered to those with ≥2 unpaid receipts
                'clubbable' => $data->count() >= 2,
            ],
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  GET /api/finance/clients-with-unbilled
     |        Step 1 of SRS wizard — clients with ≥ 2 individual invoices not
     |        yet attached to a clubbed parent.
     * ===================================================================== */
    public function clientsWithUnbilled(Request $request): JsonResponse
    {
        $rowsQ = WaterSampleInvoice::query()
            ->where('is_clubbed', false)
            ->whereNull('clubbed_invoice_id')
            ->selectRaw('invoiceable_id, invoiceable_type, COUNT(*) AS n')
            ->groupBy('invoiceable_id', 'invoiceable_type')
            ->having('n', '>=', 2);
        AuthScope::waterSampleInvoices($rowsQ, auth()->user());
        $rows = $rowsQ->get();

        $data = $rows->map(function ($r) {
            $client = ($r->invoiceable_type)::find($r->invoiceable_id);
            return [
                'invoiceable_id'   => $r->invoiceable_id,
                'invoiceable_type' => $r->invoiceable_type,
                'name'             => $client?->name ?? $client?->organization_name ?? ('Client ' . $r->invoiceable_id),
                'unbilled_count'   => (int) $r->n,
            ];
        })->values();

        return response()->json([
            'message' => 'Success',
            'data'    => $data,
        ], Http::HTTP_OK);
    }

    /* =========================================================================
     |  F-08 / F-09 / F-11 / F-13 / F-15 / F-16  POST /api/finance/clubbed-invoice
     | --------------------------------------------------------------------------
     |  All cross-row validations live in StoreClubbedInvoiceRequest. By the
     |  time we enter this method we know:
     |    • ≥ 2 unique, undeleted invoice ids
     |    • None of them are already clubbed
     |    • All share the same client AND same laboratory
     * ===================================================================== */
    public function storeClubbedInvoice(StoreClubbedInvoiceRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            // Re-lock children FOR UPDATE in transaction order to prevent a
            // concurrent request from clubbing the same ids in parallel.
            $children = WaterSampleInvoice::query()
                ->whereIn('id', $validated['invoice_ids'])
                ->lockForUpdate()
                ->with('waterSample:id,laboratory_id')
                ->get();

            // Defence-in-depth: re-check F-13 inside the lock window.
            $stillCleanIds = $children->filter(
                fn ($c) => is_null($c->clubbed_invoice_id) && !$c->is_clubbed
            )->pluck('id');
            if ($stillCleanIds->count() !== $children->count()) {
                throw new \RuntimeException('One or more invoices were clubbed by another process — aborting.');
            }

            $labId = optional($children->first()->waterSample)->laboratory_id;
            $total = (float) $children->sum('net_amount');

            $client = $this->resolveClient($validated['client_type'], (int) $validated['client_id']);

            $clubbed = WaterSampleInvoice::create([
                'invoiceable_id'          => $validated['client_id'],
                'invoiceable_type'        => $validated['client_type'],
                'price'                   => $total,
                'net_amount'              => $total,
                'paid'                    => 0,
                'balance'                 => $total,
                'status'                  => WaterSampleInvoiceStatusEnum::PENDING->value,
                'is_clubbed'              => true,
                'period_from'             => $validated['period_from']  ?? null, // D-01
                'period_to'               => $validated['period_to']    ?? null, // D-01
                'created_by'              => auth()->id(),
                'online_viewing_password' => $this->generateOnlineViewingPassword($client), // F-16
            ]);

            // F-09 — per-lab sequential slug. Falls back gracefully if lab id missing.
            $slug = $labId
                ? $this->slugService->nextClubbedSlug($labId)
                : 'C/' . Carbon::now()->format('y') . '/LAB' . $clubbed->id . '/C0001';
            $clubbed->update(['clubbed_slug' => $slug]);

            // Link the children (F-13 enforced upstream + by the lock above).
            WaterSampleInvoice::whereIn('id', $children->pluck('id'))
                ->update(['clubbed_invoice_id' => $clubbed->id]);

            // F-12 / F-16 — notify client (email) + SMS the viewing password.
            $this->notifyClient($clubbed->fresh(['childInvoices', 'invoiceable']), $client);

            return response()->json([
                'message' => 'Clubbed Invoice generated successfully',
                'data'    => $clubbed->fresh([
                    'childInvoices.waterSample.waterSampleDetails.test',
                    'invoiceable',
                ]),
            ], Http::HTTP_CREATED);
        });
    }

    /* =========================================================================
     |  F-03 / F-14  POST /api/finance/record-payment/{waterSampleInvoice}
     | --------------------------------------------------------------------------
     |  Adds (does NOT overwrite) `paid`; persists the full audit trail of
     |  Date of Receipt / Mode / Receipt-No / Received-By; for clubbed
     |  invoices, distributes proportionally to constituents.
     * ===================================================================== */
    public function recordPayment(RecordPaymentRequest $request, WaterSampleInvoice $waterSampleInvoice): JsonResponse
    {
        $validated = $request->validated();

        $amount = round((float) $validated['amount'], 2);

        if (bccomp((string) $amount, (string) ($waterSampleInvoice->balance + 0.001), 2) > 0) {
            return response()->json(['message' => 'Exceeds balance'], Http::HTTP_UNPROCESSABLE_ENTITY);
        }

        $paymentDate = $validated['payment_date'] ?? now()->toDateString();
        $receiptNo   = $validated['receipt_no']   ?? ($validated['reference'] ?? null);

        return DB::transaction(function () use ($waterSampleInvoice, $amount, $validated, $paymentDate, $receiptNo) {
            $newPaid = (float) $waterSampleInvoice->paid + $amount;
            $newBal  = max(0, (float) $waterSampleInvoice->net_amount - $newPaid);
            $status  = $newBal <= 0 ? WaterSampleInvoiceStatusEnum::PAID->value : WaterSampleInvoiceStatusEnum::PARTIAL->value;

            // Parent log
            $waterSampleInvoice->waterSampleInvoiceLogs()->create([
                'user_id'          => auth()->id(),
                'paid'             => $amount,
                'balance'          => $newBal,
                'payment_mode'     => $validated['payment_mode'],
                'note'             => $validated['remarks']      ?? null,
                'payment_date'     => $paymentDate,
                'receipt_no'       => $receiptNo,
                'received_by_name' => $validated['received_by']  ?? auth()->user()?->name,
            ]);

            $waterSampleInvoice->update([
                'paid'    => $newPaid,
                'balance' => $newBal,
                'status'  => $status,
            ]);

            // F-14 — proportional distribution for clubbed parents
            if ($waterSampleInvoice->is_clubbed) {
                $this->distributeToChildren($waterSampleInvoice, $amount, $validated, $paymentDate, $receiptNo);
            }

            return response()->json(['message' => 'Payment recorded successfully']);
        });
    }

    /* =========================================================================
     |  F-10 / F-12  GET /api/finance/clubbed-invoices/{id}/pdf
     |               Renders the SRS-compliant clubbed-invoice template.
     * ===================================================================== */
    public function clubbedPdf(WaterSampleInvoice $waterSampleInvoice)
    {
        if (!$waterSampleInvoice->is_clubbed) {
            return response()->json([
                'message' => 'This endpoint is only valid for clubbed invoices.',
            ], Http::HTTP_BAD_REQUEST);
        }

        $waterSampleInvoice->load([
            'invoiceable',
            'childInvoices.waterSample.laboratory:id,name,code,address,email,phone',
            'childInvoices.waterSample.waterSampleDetails.test',
        ]);

        $payload = [
            'waterSampleInvoice' => $waterSampleInvoice,
            'client'             => $waterSampleInvoice->invoiceable,
            'childCount'         => $waterSampleInvoice->childInvoices->count(),
            'billingSummary'     => $waterSampleInvoice->billing_summary,
        ];

        // F-10 — render the SRS-compliant clubbed-invoice template. If
        // wkhtmltopdf is not installed on the server (a known deployment
        // dependency — see also the existing `WaterSampleInvoiceController::
        // generatePdf`), fall back to the HTML view so the SRS-format
        // invoice is still produced and the endpoint never 500s.
        if (class_exists(\PDF::class)) {
            try {
                $pdf = \PDF::loadView('waterSample.clubbed-invoice', $payload);
                $pdf->setOption('page-size', 'A4');
                $fileName = 'clubbed-invoice-' . str_replace('/', '-', $waterSampleInvoice->clubbed_slug ?? $waterSampleInvoice->id) . '.pdf';
                return $pdf->download($fileName);
            } catch (\Throwable $e) {
                Log::warning('wkhtmltopdf failed, returning HTML', [
                    'invoice_id' => $waterSampleInvoice->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
        return response()->view('waterSample.clubbed-invoice', $payload);
    }

    /* ---------- Private helpers --------------------------------------------- */

    private function normalizeStatusFilter(string $raw): ?string
    {
        $raw = strtolower(trim($raw));
        return match ($raw) {
            'unpaid', 'pending'                            => 'pending',
            'partial', 'partially paid', 'partially_paid'  => 'partial',
            'paid'                                          => 'paid',
            'all'                                           => null,
            default                                         => null,
        };
    }

    private function buildLightSummary($rows): array
    {
        return [
            'total_invoiced'    => (float) $rows->sum('total'),
            'total_collected'   => (float) $rows->sum('received'),
            'total_outstanding' => (float) $rows->sum('balance'),
        ];
    }

    /** @return array{0: \Illuminate\Database\Eloquent\Builder, 1: \Illuminate\Database\Eloquent\Builder} */
    private function buildFilteredQueries(Request $request): array
    {
        $invoiceQuery = WaterSampleInvoice::query();
        $logQuery     = WaterSampleInvoiceLog::query();

        // RBAC: scope by user's visible labs before applying request filters.
        // This means revenueSummary aggregates respect role scope as well.
        $user = auth()->user();
        AuthScope::waterSampleInvoices($invoiceQuery, $user);
        AuthScope::waterSampleInvoiceLogs($logQuery, $user);

        if ($request->filled('lab_id')) {
            $labId = (int) $request->lab_id;
            $invoiceQuery->whereHas('waterSample', fn ($q) => $q->where('laboratory_id', $labId));
            $logQuery->whereHas('waterSampleInvoice.waterSample', fn ($q) => $q->where('laboratory_id', $labId));
        }
        if ($request->filled('client_id')) {
            $cid = (int) $request->client_id;
            $invoiceQuery->where('invoiceable_id', $cid);
            $logQuery->whereHas('waterSampleInvoice', fn ($q) => $q->where('invoiceable_id', $cid));
        }
        if ($request->filled('district_id')) {
            $did = (int) $request->district_id;
            $invoiceQuery->whereHas('waterSample', fn ($q) => $q->where('district_id', $did));
            $logQuery->whereHas('waterSampleInvoice.waterSample', fn ($q) => $q->where('district_id', $did));
        }
        if ($request->filled('date_from')) {
            $invoiceQuery->whereDate('created_at', '>=', $request->date_from);
            $logQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $invoiceQuery->whereDate('created_at', '<=', $request->date_to);
            $logQuery->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $key = $this->normalizeStatusFilter($request->status);
            if ($key !== null) {
                $invoiceQuery->where('status', $key);
            }
        }
        return [$invoiceQuery, $logQuery];
    }

    private function resolveClient(string $type, int $id)
    {
        try {
            if (!class_exists($type)) {
                return null;
            }
            return ($type)::find($id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * F-16 — Online viewing password.
     *
     * The SRS specifies this is "the mobile number of the client", printed on
     * the invoice header and emailed/SMSed to the client. We mirror that:
     * fall back to a random 8-digit value if no phone is on file so the
     * column is never null.
     */
    private function generateOnlineViewingPassword($client): string
    {
        $phone = $client?->phone;
        if (!empty($phone)) {
            return (string) $phone;
        }
        return (string) random_int(10000000, 99999999);
    }

    private function notifyClient(WaterSampleInvoice $clubbed, $client): void
    {
        if (!$client) {
            return;
        }
        try {
            if (!empty($client->email)) {
                $client->notify(new ClubbedInvoiceGenerated($clubbed));
            }
            $this->smsService->send(
                $client->phone ?? null,
                "Clubbed Invoice {$clubbed->clubbed_slug} generated. "
                . "Total: PKR " . number_format((float) $clubbed->net_amount) . ". "
                . "Online viewing password: {$clubbed->online_viewing_password}"
            );
        } catch (\Throwable $e) {
            // Email/SMS failure must NOT roll back the invoice creation.
            Log::warning('Clubbed invoice notification failed', [
                'invoice_id' => $clubbed->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function distributeToChildren(WaterSampleInvoice $parent, float $amount, array $validated, string $paymentDate, ?string $receiptNo): void
    {
        $children = $parent->childInvoices()->get();
        $parentNet = (float) $parent->net_amount;
        if ($parentNet <= 0) {
            return;
        }

        // Allocate by ratio, then assign rounding remainder to the largest child
        // to guarantee Σchildren.paid_delta == amount.
        $allocated = 0.0;
        $rows = [];
        foreach ($children as $child) {
            $ratio    = (float) $child->net_amount / $parentNet;
            $portion  = round($amount * $ratio, 2);
            $allocated += $portion;
            $rows[]   = [$child, $portion];
        }
        $rounding = round($amount - $allocated, 2);
        if (abs($rounding) > 0.001 && !empty($rows)) {
            usort($rows, fn ($a, $b) => (float) $b[0]->net_amount <=> (float) $a[0]->net_amount);
            $rows[0][1] = round($rows[0][1] + $rounding, 2);
        }

        foreach ($rows as [$child, $portion]) {
            $cPaid = (float) $child->paid + $portion;
            $cBal  = max(0, (float) $child->net_amount - $cPaid);
            $cStat = $cBal <= 0 ? WaterSampleInvoiceStatusEnum::PAID->value : WaterSampleInvoiceStatusEnum::PARTIAL->value;

            $child->waterSampleInvoiceLogs()->create([
                'user_id'          => auth()->id(),
                'paid'             => $portion,
                'balance'          => $cBal,
                'payment_mode'     => $validated['payment_mode'],
                'note'             => 'Distributed from Clubbed Invoice ' . ($parent->clubbed_slug ?? 'C-' . $parent->id),
                'payment_date'     => $paymentDate,
                'receipt_no'       => $receiptNo,
                'received_by_name' => $validated['received_by'] ?? auth()->user()?->name,
            ]);

            $child->update([
                'paid'    => $cPaid,
                'balance' => $cBal,
                'status'  => $cStat,
            ]);
        }
    }
}
