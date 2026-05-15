<?php

namespace Database\Seeders;

use App\Models\Laboratories\Laboratory;
use App\Models\SbpSubmission;
use App\Models\User;
use App\Models\WaterSamples\WaterSampleInvoice;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds demo data for the SBP Submissions page so the UI has content
 * to show end-to-end:
 *
 *   • Pending Logs (modal)  — Cash/Cheque payments not yet banked
 *   • Submissions table     — historical SBP deposits, mix of submitted + verified
 *
 * Idempotent: re-running is safe — rows are looked up by stable marker
 * strings (receipt_no / challan_no) and only inserted when missing.
 */
class SbpSubmissionSeeder extends Seeder
{
    public function run(): void
    {
        $lab = Laboratory::query()->orderBy('id')->first();
        $user = User::query()->orderBy('id')->first();
        if (!$lab || !$user) {
            $this->command?->warn('SbpSubmissionSeeder: no Laboratory/User found — skipping.');
            return;
        }

        $invoices = WaterSampleInvoice::query()->orderBy('id')->take(6)->get();
        if ($invoices->isEmpty()) {
            $this->command?->warn('SbpSubmissionSeeder: no WaterSampleInvoice rows — skipping.');
            return;
        }

        DB::transaction(function () use ($lab, $user, $invoices) {
            $now = now();

            // ── 1) Pending logs (not yet banked) ────────────────────────────
            // These appear in the "+ New SBP Submission" modal's selection list.
            $pendingPlan = [
                ['mode' => 'Cash',   'paid' => 725,  'receipt' => 'CSH-DEMO-001'],
                ['mode' => 'Cheque', 'paid' => 725,  'receipt' => 'CHQ-DEMO-002'],
                ['mode' => 'Cash',   'paid' => 1450, 'receipt' => 'CSH-DEMO-003'],
            ];
            foreach ($pendingPlan as $i => $plan) {
                if (WaterSampleInvoiceLog::where('receipt_no', $plan['receipt'])->exists()) continue;
                $inv = $invoices->get($i % $invoices->count());
                if (!$inv) continue;

                WaterSampleInvoiceLog::create([
                    'water_sample_invoice_id' => $inv->id,
                    'sbp_submission_id'       => null,
                    'user_id'                 => $user->id,
                    'received_by_name'        => 'Seeded Demo',
                    'paid'                    => $plan['paid'],
                    'balance'                 => 0,
                    'payment_date'            => $now->copy()->subDays($i + 1)->toDateString(),
                    'receipt_no'              => $plan['receipt'],
                    'payment_mode'            => $plan['mode'],
                    'note'                    => 'Demo seed — pending bank deposit',
                    'created_at'              => $now->copy()->subDays($i + 1),
                    'updated_at'              => $now->copy()->subDays($i + 1),
                ]);
            }

            // ── 2) Historical SBP submissions (already banked) ──────────────
            $submissionsPlan = [
                [
                    'challan'        => 'SBP-DEMO-2026-001',
                    'status'         => 'submitted',
                    'submitted_by'   => 'S.M. Adeel',
                    'deposit_offset' => 14,  // days ago
                    'logs'           => [
                        ['mode' => 'Cash',   'paid' => 725,  'receipt' => 'CSH-SBP1-A'],
                        ['mode' => 'Cheque', 'paid' => 1450, 'receipt' => 'CHQ-SBP1-B'],
                    ],
                ],
                [
                    'challan'        => 'SBP-DEMO-2026-002',
                    'status'         => 'verified',
                    'submitted_by'   => 'Ms. Saima',
                    'deposit_offset' => 30,
                    'verified'       => true,
                    'logs'           => [
                        ['mode' => 'Cash',   'paid' => 725, 'receipt' => 'CSH-SBP2-A'],
                        ['mode' => 'Cash',   'paid' => 725, 'receipt' => 'CSH-SBP2-B'],
                        ['mode' => 'Cheque', 'paid' => 725, 'receipt' => 'CHQ-SBP2-C'],
                    ],
                ],
            ];

            $seqStart = (int) ($lab->next_sbp_seq ?? 0);
            $created = 0;

            foreach ($submissionsPlan as $idx => $plan) {
                if (SbpSubmission::where('challan_no', $plan['challan'])->exists()) continue;

                $depositAt    = $now->copy()->subDays($plan['deposit_offset']);
                $periodFrom   = $depositAt->copy()->startOfMonth();
                $periodTo     = $depositAt->copy()->endOfMonth();
                $amount       = collect($plan['logs'])->sum('paid');
                $submissionNo = $seqStart + $created + 1;
                $slug         = sprintf('SBP/%s/%s/%04d',
                    $depositAt->format('y'),
                    strtoupper((string) ($lab->code ?? 'LAB')),
                    $submissionNo);

                $sbp = SbpSubmission::create([
                    'submission_slug'   => $slug,
                    'laboratory_id'     => $lab->id,
                    'period_from'       => $periodFrom->toDateString(),
                    'period_to'         => $periodTo->toDateString(),
                    'amount'            => $amount,
                    'challan_no'        => $plan['challan'],
                    'deposit_date'      => $depositAt->toDateString(),
                    'submitted_by_id'   => $user->id,
                    'submitted_by_name' => $plan['submitted_by'],
                    'status'            => $plan['status'],
                    'remarks'           => 'Seeded demo submission',
                    'verified_at'       => !empty($plan['verified']) ? $depositAt->copy()->addDays(2) : null,
                    'verified_by_id'    => !empty($plan['verified']) ? $user->id : null,
                    'created_at'        => $depositAt,
                    'updated_at'        => $depositAt,
                ]);
                $created++;

                foreach ($plan['logs'] as $li => $logPlan) {
                    if (WaterSampleInvoiceLog::where('receipt_no', $logPlan['receipt'])->exists()) continue;
                    $inv = $invoices->get(($idx * 2 + $li) % $invoices->count());
                    if (!$inv) continue;

                    WaterSampleInvoiceLog::create([
                        'water_sample_invoice_id' => $inv->id,
                        'sbp_submission_id'       => $sbp->id,
                        'user_id'                 => $user->id,
                        'received_by_name'        => 'Seeded Demo',
                        'paid'                    => $logPlan['paid'],
                        'balance'                 => 0,
                        'payment_date'            => $depositAt->copy()->subDays(1)->toDateString(),
                        'receipt_no'              => $logPlan['receipt'],
                        'payment_mode'            => $logPlan['mode'],
                        'note'                    => 'Demo seed — already banked',
                        'created_at'              => $depositAt->copy()->subDays(1),
                        'updated_at'              => $depositAt->copy()->subDays(1),
                    ]);
                }
            }

            if ($created > 0) {
                $lab->update(['next_sbp_seq' => $seqStart + $created]);
            }
        });

        $this->command?->info('SbpSubmissionSeeder: demo SBP submissions and invoice logs seeded.');
    }
}
