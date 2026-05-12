<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SbpSubmission;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SbpSubmissionController extends Controller
{
    public function index()
    {
        \Log::info('SBP Index hit');
        return SbpSubmission::with(['laboratory', 'submittedBy'])
            ->withCount('invoiceLogs')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get pending cash collections that are not yet banked (SBP submitted)
     */
    public function pending()
    {
        \Log::info('SBP Pending hit');
        return WaterSampleInvoiceLog::with(['waterSampleInvoice.invoiceable', 'waterSampleInvoice.waterSample.laboratory'])
            ->whereNull('sbp_submission_id')
            ->whereIn('payment_mode', ['Cash/Cheque', 'Cash', 'Cheque'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'log_ids'           => 'required|array',
            'challan_no'        => 'required|string',
            'deposit_date'      => 'required|date',
            'period_from'       => 'nullable|date',
            'period_to'         => 'nullable|date',
            'lab_id'            => 'required|exists:laboratories,id',
            'submitted_by_name' => 'nullable|string',
            'remarks'           => 'nullable|string'
        ]);

        return DB::transaction(function () use ($request) {
            $logs = WaterSampleInvoiceLog::whereIn('id', $request->log_ids)->get();
            $totalAmount = $logs->sum('paid');

            $submission = SbpSubmission::create([
                'submission_slug'   => 'SBP/' . Carbon::now()->format('y') . '/CLB/' . str_pad(SbpSubmission::count() + 1, 4, '0', STR_PAD_LEFT),
                'laboratory_id'     => $request->lab_id,
                'amount'            => $totalAmount,
                'challan_no'        => $request->challan_no,
                'deposit_date'      => $request->deposit_date,
                'period_from'       => $request->period_from,
                'period_to'         => $request->period_to,
                'submitted_by_id'   => Auth::id(),
                'submitted_by_name' => $request->submitted_by_name ?? Auth::user()->name,
                'status'            => 'submitted',
                'remarks'           => $request->remarks,
            ]);

            // Mark logs as banked
            WaterSampleInvoiceLog::whereIn('id', $request->log_ids)
                ->update(['sbp_submission_id' => $submission->id]);

            return response()->json([
                'message' => 'SBP Submission recorded successfully',
                'submission' => $submission
            ]);
        });
    }

    public function verify($id)
    {
        $submission = SbpSubmission::findOrFail($id);
        $submission->update([
            'status'      => 'verified',
            'verified_at' => Carbon::now(),
            'verified_by_id' => Auth::id()
        ]);

        return response()->json([
            'message' => 'SBP Submission verified successfully',
            'submission' => $submission
        ]);
    }
}
