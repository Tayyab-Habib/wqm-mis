<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Client;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleInvoice;
use Carbon\Carbon;
use Illuminate\Support\Str;

class FinanceParitySeeder extends Seeder
{
    public function run()
    {
        $adminUser = User::where('email', 'system.administrator@mis.com')->first() ?? User::query()->first();
        
        $districtId = DB::table('districts')->value('id') ?? 1;
        $divisionId = DB::table('divisions')->value('id') ?? 1;
        $provinceId = DB::table('provinces')->value('id') ?? 1;
        $tehsilId   = DB::table('tehsils')->value('id') ?? 1;
        $schemeId   = DB::table('water_schemes')->value('id') ?? 1;
        $labId      = DB::table('laboratories')->value('id') ?? 1;

        // Clean up
        DB::table('water_sample_invoice_logs')->delete();
        DB::table('water_sample_invoices')->delete();
        DB::table('water_sample_details')->delete();
        // We won't delete all water samples as they might be used elsewhere, 
        // but we'll flag ours with specific slugs or just create new ones.

        $clients = [
            ['name' => 'NESPAK Ltd.', 'type' => 'private'],
            ['name' => 'Health Department KP', 'type' => 'government'],
            ['name' => 'WAPDA Colony', 'type' => 'government'],
            ['name' => 'Individual Resident - Hayatabad', 'type' => 'private'],
            ['name' => 'Peshawar Model School', 'type' => 'private'],
        ];

        foreach ($clients as $cData) {
            $clientId = DB::table('clients')->insertGetId([
                'name' => $cData['name'],
                'type' => $cData['type'],
                'email' => Str::slug($cData['name']) . '@example.com',
                'phone' => '091-' . rand(1000000, 9999999),
                'address' => $cData['name'] . ' Main Office',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // For each client, create 5-10 samples
            $count = ($cData['name'] === 'Individual Resident - Hayatabad') ? 1 : rand(5, 8);
            
            $childInvoiceIds = [];

            for ($i = 1; $i <= $count; $i++) {
                $date = now()->subDays(rand(1, 30));
                $slug = '26/' . (rand(0,1) ? 'PWR' : 'MRD') . '/PHE/' . rand(6000, 9999);

                $sampleId = DB::table('water_samples')->insertGetId([
                    'water_scheme_id'  => $schemeId,
                    'laboratory_id'    => $labId,
                    'division_id'      => $divisionId,
                    'province_id'      => $provinceId,
                    'district_id'      => $districtId,
                    'tehsil_id'        => $tehsilId,
                    'slug'             => $slug,
                    'test_type'        => 'fresh',
                    'source_type'      => 'gravity',
                    'collectable_id'   => $clientId,
                    'collectable_type' => Client::class,
                    'created_by'       => $adminUser->id,
                    'modified_by'      => $adminUser->id,
                    'created_at'       => $date,
                    'updated_at'       => $date,
                ]);

                // Randomly assign tests (P, C, M)
                // 1=P, 7=C, 21=M
                $testPool = [1, 7, 21];
                $selectedTests = [];
                $dice = rand(1, 4);
                if ($dice === 1) $selectedTests = [1, 7, 21]; // PCM
                elseif ($dice === 2) $selectedTests = [1, 7];    // PC
                elseif ($dice === 3) $selectedTests = [21];      // M
                else $selectedTests = [1, 21];                   // PM

                $totalRate = 0;
                foreach ($selectedTests as $tId) {
                    DB::table('water_sample_details')->insert([
                        'water_sample_id' => $sampleId,
                        'test_id' => $tId,
                        'analysis_result' => 'Fit',
                        'created_at' => $date,
                    ]);
                    // Mock rate logic
                    if ($tId == 1) $totalRate += 600;
                    if ($tId == 7) $totalRate += 600;
                    if ($tId == 21) $totalRate += 900;
                }

                // Create Individual Invoice
                $invoiceId = DB::table('water_sample_invoices')->insertGetId([
                    'water_sample_id'  => $sampleId,
                    'invoiceable_id'   => $clientId,
                    'invoiceable_type' => Client::class,
                    'price'            => $totalRate,
                    'net_amount'       => $totalRate,
                    'paid'             => 0,
                    'balance'          => $totalRate,
                    'status'           => 'pending',
                    'is_clubbed'       => 0,
                    'created_by'       => $adminUser->id,
                    'modified_by'      => $adminUser->id,
                    'created_at'       => $date,
                    'updated_at'       => $date,
                ]);

                $childInvoiceIds[] = $invoiceId;
            }

            // For Govt clients, let's create one Clubbed Invoice from these samples
            if ($cData['type'] === 'government' && count($childInvoiceIds) >= 2) {
                $parentTotal = 0;
                foreach ($childInvoiceIds as $cid) {
                    $parentTotal += DB::table('water_sample_invoices')->where('id', $cid)->value('net_amount');
                }

                $clubbedSlug = 'C/' . now()->year . '/CLB/' . rand(1000, 9999);
                $parentId = DB::table('water_sample_invoices')->insertGetId([
                    'invoiceable_id'   => $clientId,
                    'invoiceable_type' => Client::class,
                    'price'            => $parentTotal,
                    'net_amount'       => $parentTotal,
                    'paid'             => 0,
                    'balance'          => $parentTotal,
                    'status'           => 'pending',
                    'is_clubbed'       => 1,
                    'clubbed_slug'     => $clubbedSlug,
                    'created_by'       => $adminUser->id,
                    'modified_by'      => $adminUser->id,
                    'created_at'       => now()->subDays(15),
                    'updated_at'       => now()->subDays(15),
                ]);

                // Link children
                DB::table('water_sample_invoices')
                    ->whereIn('id', $childInvoiceIds)
                    ->update(['clubbed_invoice_id' => $parentId]);

                // Record Multiple Ledger Entries for this clubbed invoice
                if ($cData['name'] === 'Health Department KP' || $cData['name'] === 'WAPDA Colony') {
                    // Payment 1: 30% via SBP
                    $pay1 = round($parentTotal * 0.3, 2);
                    $this->addLedgerEntry($parentId, $pay1, 'SBP', 'Initial budget release', $adminUser->id, now()->subDays(10), $childInvoiceIds, $parentTotal);
                    
                    // Payment 2: 20% via Cheque (recorded later)
                    $pay2 = round($parentTotal * 0.2, 2);
                    $this->addLedgerEntry($parentId, $pay2, 'Cheque', 'Second installment', $adminUser->id, now()->subDays(5), $childInvoiceIds, $parentTotal);
                }
            } else if ($cData['name'] === 'Peshawar Model School') {
                // Fully Paid Individual Invoices
                foreach ($childInvoiceIds as $cid) {
                    $net = DB::table('water_sample_invoices')->where('id', $cid)->value('net_amount');
                    $this->addLedgerEntry($cid, $net, 'Cash', 'Full payment at counter', $adminUser->id, now()->subDays(2), [], $net);
                }
            }
        }

        echo "Finance Parity & Ledger Data Seeded Successfully!\n";
    }

    private function addLedgerEntry($invoiceId, $amount, $mode, $note, $userId, $date, $childIds, $totalAmount)
    {
        // Update Invoice
        $inv = DB::table('water_sample_invoices')->where('id', $invoiceId)->first();
        $newPaid = $inv->paid + $amount;
        $newBalance = $inv->net_amount - $newPaid;
        $status = ($newBalance <= 0) ? 'paid' : 'partial';

        DB::table('water_sample_invoices')->where('id', $invoiceId)->update([
            'paid' => $newPaid,
            'balance' => $newBalance,
            'status' => $status,
            'updated_at' => $date
        ]);

        // Record Log
        DB::table('water_sample_invoice_logs')->insert([
            'water_sample_invoice_id' => $invoiceId,
            'paid' => $amount,
            'balance' => $newBalance,
            'payment_mode' => $mode,
            'note' => $note,
            'user_id' => $userId,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        // Distribute to children if parent
        if (count($childIds) > 0) {
            foreach ($childIds as $cid) {
                $child = DB::table('water_sample_invoices')->where('id', $cid)->first();
                $ratio = $child->net_amount / $totalAmount;
                $childPaidContribution = round($amount * $ratio, 2);
                
                $cNewPaid = $child->paid + $childPaidContribution;
                $cNewBal = $child->net_amount - $cNewPaid;
                $cStatus = ($cNewBal <= 0) ? 'paid' : 'partial';

                DB::table('water_sample_invoices')->where('id', $cid)->update([
                    'paid' => $cNewPaid,
                    'balance' => $cNewBal,
                    'status' => $cStatus,
                    'updated_at' => $date
                ]);
            }
        }
    }
}
