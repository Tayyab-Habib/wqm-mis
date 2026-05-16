<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SbpSubmission;
use App\Models\WaterSamples\WaterSampleInvoice;
use App\Models\WaterSamples\WaterSampleInvoiceLog;
use App\Models\User;
use Carbon\Carbon;

class SbpSubmissionSeeder extends Seeder
{
    public function run()
    {
        $user = User::first() ?? User::factory()->create();
        
        // 1. Create some pending invoice logs (not yet in SBP)
        $invoices = WaterSampleInvoice::take(20)->get();
        if ($invoices->isEmpty()) {
            return;
        }

        foreach ($invoices->take(5) as $inv) {
            WaterSampleInvoiceLog::create([
                'water_sample_invoice_id' => $inv->id,
                'user_id' => $user->id,
                'paid' => 120000,
                'balance' => 0,
                'payment_mode' => 'Cash',
                'note' => 'Test collection for SBP pending',
                'created_at' => Carbon::now()->subDays(rand(1, 10))
            ]);
        }

        // 2. Create some already submitted SBP deposits
        $submission1 = SbpSubmission::create([
            'submission_slug' => 'SBP/26/CLB/0041',
            'laboratory_id' => 1,
            'period_from' => Carbon::now()->subMonth()->startOfMonth(),
            'period_to' => Carbon::now()->subMonth()->endOfMonth(),
            'amount' => 1800000,
            'challan_no' => 'SBP-2026-04471',
            'deposit_date' => Carbon::now()->subDays(15),
            'submitted_by_id' => $user->id,
            'submitted_by_name' => 'S.M. Adeel',
            'status' => 'verified',
            'remarks' => 'Monthly revenue deposit'
        ]);

        foreach ($invoices->slice(5, 3) as $inv) {
            WaterSampleInvoiceLog::create([
                'water_sample_invoice_id' => $inv->id,
                'user_id' => $user->id,
                'paid' => 600000,
                'balance' => 0,
                'payment_mode' => 'Cash',
                'sbp_submission_id' => $submission1->id,
                'created_at' => Carbon::now()->subMonths(1)
            ]);
        }

        $submission2 = SbpSubmission::create([
            'submission_slug' => 'SBP/26/CLB/0042',
            'laboratory_id' => 1,
            'period_from' => Carbon::now()->subDays(20),
            'period_to' => Carbon::now()->subDays(1),
            'amount' => 600000,
            'challan_no' => 'Pending',
            'deposit_date' => Carbon::now()->subDays(2),
            'submitted_by_id' => $user->id,
            'submitted_by_name' => 'S.M. Adeel',
            'status' => 'submitted',
            'remarks' => 'Partial collection deposit'
        ]);

        foreach ($invoices->slice(8, 2) as $inv) {
            WaterSampleInvoiceLog::create([
                'water_sample_invoice_id' => $inv->id,
                'user_id' => $user->id,
                'paid' => 300000,
                'balance' => 0,
                'payment_mode' => 'Cheque',
                'sbp_submission_id' => $submission2->id,
                'created_at' => Carbon::now()->subDays(5)
            ]);
        }
    }
}
