<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreSbpSubmissionRequest;
use App\Models\SbpSubmission;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use App\Services\AuthScope;
use App\Services\FinanceSlugService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as Http;

class SbpSubmissionController extends Controller
{
    public function __construct(
        private readonly FinanceSlugService $slugService,
    ) {
    }

    public function index(): JsonResponse
    {
        $query = SbpSubmission::query()
            ->with([
                'laboratory:id,name,code',
                'submittedBy:id,name',
                'verifiedBy:id,name',
            ])
            ->withCount('invoiceLogs')
            ->orderByDesc('created_at');

        // RBAC: scope to user's visible labs via sbp_submissions.lab_id.
        AuthScope::sbpSubmissions($query, auth()->user());
        $rows = $query->get();

        // Same reason as pending(): expose a parseable ISO timestamp
        // alongside the trait-formatted `created_at` string.
        $rows->each(function ($row) {
            $row->created_at_iso = $row->getRawOriginal('created_at');
        });

        return response()->json([
            'message' => 'Success',
            'data'    => $rows,
        ], Http::HTTP_OK);
    }

    /**
     * D-02 fix.
     *
     * The previous version did `->with(['waterSampleInvoice.waterSample.laboratory'])`
     * which transitively serialised `water_samples.test_type` through
     * `TestFrequencyEnum`, whose cases ('Fresh', 'Retest', ...) don't match
     * the actual data (lowercase 'fresh'). That triggered a `ValueError` and
     * the endpoint returned HTTP 500.
     *
     * The remedy: eager-load only the columns we actually need from each
     * relation. By projecting `id, slug, laboratory_id` from `water_samples`
     * we never read `test_type`, so the broken cast is never invoked.
     */
    public function pending(): JsonResponse
    {
        $query = WaterSampleInvoiceLog::query()
            ->whereNull('sbp_submission_id')
            ->whereIn('payment_mode', ['Cash', 'Cheque'])
            ->with([
                'waterSampleInvoice:id,water_sample_id,invoiceable_id,invoiceable_type,is_clubbed,clubbed_slug,net_amount',
                'waterSampleInvoice.invoiceable',
                'waterSampleInvoice.waterSample:id,slug,laboratory_id',
                'waterSampleInvoice.waterSample.laboratory:id,name,code',
                'user:id,name',
            ])
            ->orderBy('created_at', 'asc');

        // RBAC: scope through invoice's water_sample lab.
        AuthScope::waterSampleInvoiceLogs($query, auth()->user());

        $logs = $query->get([
                'id',
                'water_sample_invoice_id',
                'user_id',
                'paid',
                'balance',
                'payment_mode',
                'note',
                'payment_date',
                'receipt_no',
                'received_by_name',
                'created_at',
                'sbp_submission_id',
            ]);

        // TimeStampAccessorTrait reformats `created_at` as a human string
        // ("17 May, 2026 10:32") which JS Date() can't parse. Surface the raw
        // ISO timestamp so the frontend's period-range filter works.
        $logs->each(function ($log) {
            $log->created_at_iso = $log->getRawOriginal('created_at');
        });

        return response()->json([
            'message' => 'Success',
            'data'    => $logs,
        ], Http::HTTP_OK);
    }

    public function store(StoreSbpSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated) {
            $logs = WaterSampleInvoiceLog::whereIn('id', $validated['log_ids'])
                ->lockForUpdate()
                ->get();

            // Re-check inside the lock (Form Request already validated, but
            // a race could have changed state in between).
            $reBanked = $logs->filter(fn ($l) => !is_null($l->sbp_submission_id));
            if ($reBanked->isNotEmpty()) {
                return response()->json([
                    'message' => 'One or more selected logs have already been submitted to SBP: '
                                 . $reBanked->pluck('id')->implode(', '),
                ], Http::HTTP_CONFLICT);
            }

            $amount = (float) $logs->sum('paid');

            // D-04 — per-lab sequential slug (replaces hard-coded "CLB" + global counter).
            $slug = $this->slugService->nextSbpSlug((int) $validated['lab_id']);

            $submission = SbpSubmission::create([
                'submission_slug'   => $slug,
                'laboratory_id'     => $validated['lab_id'],
                'amount'            => $amount,
                'challan_no'        => $validated['challan_no'],
                'deposit_date'      => $validated['deposit_date'],
                'period_from'       => $validated['period_from'] ?? null,
                'period_to'         => $validated['period_to']   ?? null,
                'submitted_by_id'   => Auth::id(),
                'submitted_by_name' => $validated['submitted_by_name'] ?? Auth::user()?->name,
                'status'            => 'submitted',
                'remarks'           => $validated['remarks']         ?? null,
                'attachment_path'   => $validated['attachment_path'] ?? null,
            ]);

            WaterSampleInvoiceLog::whereIn('id', $logs->pluck('id'))
                ->update(['sbp_submission_id' => $submission->id]);

            return response()->json([
                'message'    => 'SBP Submission recorded successfully',
                'submission' => $submission,
            ], Http::HTTP_CREATED);
        });
    }

    /**
     * D-05 — Segregation of duties on SBP verify.
     *
     * • The verifier must be a `system-administrator` (or any role granted
     *   the `verify-sbp-submission` permission, if added later).
     * • The verifier MUST NOT be the same user who submitted the row.
     */
    public function verify(int $id): JsonResponse
    {
        $submission = SbpSubmission::findOrFail($id);
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], Http::HTTP_UNAUTHORIZED);
        }

        // SBP verification is an admin-tier action. isUnscoped() covers
        // system-administrator + system-manager + view-only-admin (the audit
        // role); finance-verifier doesn't exist in our roles table so the
        // legacy hasAnyRole(['system-administrator', 'finance-verifier'])
        // would 403 every non-SA user that should be allowed.
        if (!$user->isUnscoped()) {
            return response()->json([
                'message' => 'Only admin-tier users may verify SBP submissions.',
            ], Http::HTTP_FORBIDDEN);
        }

        if ($submission->submitted_by_id === $user->id) {
            return response()->json([
                'message' => 'Segregation of duties: the user who submitted an SBP record cannot verify it.',
            ], Http::HTTP_FORBIDDEN);
        }

        if ($submission->status === 'verified') {
            return response()->json([
                'message' => 'Submission has already been verified.',
                'submission' => $submission,
            ], Http::HTTP_CONFLICT);
        }

        $submission->update([
            'status'         => 'verified',
            'verified_at'    => Carbon::now(),
            'verified_by_id' => $user->id,
        ]);

        return response()->json([
            'message'    => 'SBP Submission verified successfully',
            'submission' => $submission,
        ], Http::HTTP_OK);
    }
}
