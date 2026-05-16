<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Client;
use Carbon\Carbon;

class FinanceInvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminUser = User::where('email', 'system.administrator@mis.com')->first() ?? User::query()->first();

        // 1. Setup required dependencies
        $districtId = DB::table('districts')->value('id') ?? 1;
        $divisionId = DB::table('divisions')->value('id') ?? 1;
        $provinceId = DB::table('provinces')->value('id') ?? 1;
        $tehsilId   = DB::table('tehsils')->value('id') ?? 1;

        $schemeId = DB::table('water_schemes')->value('id');
        if (!$schemeId) {
            $schemeId = DB::table('water_schemes')->insertGetId([
                'name'        => 'Central WSS Peshawar',
                'is_active'   => 1,
                'district_id' => $districtId,
                'division_id' => $divisionId,
                'province_id' => $provinceId,
                'tehsil_id'   => $tehsilId,
                'created_by'  => $adminUser->id,
                'modified_by' => $adminUser->id,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $centralLabId = DB::table('laboratories')->where('name', 'like', '%Central Lab%')->value('id');
        if (!$centralLabId) {
            $centralLabId = DB::table('laboratories')->insertGetId([
                'name' => 'Central Lab - Peshawar', 'is_active' => 1, 'province_id' => $provinceId, 'district_id' => $districtId,
                'created_by' => $adminUser->id, 'modified_by' => $adminUser->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $mardanLabId = DB::table('laboratories')->where('name', 'like', '%Mardan%')->value('id');
        if (!$mardanLabId) {
            $mardanLabId = DB::table('laboratories')->insertGetId([
                'name' => 'Mardan', 'is_active' => 1, 'province_id' => $provinceId, 'district_id' => $districtId,
                'created_by' => $adminUser->id, 'modified_by' => $adminUser->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Explicit Dummy Data based on exact image
        $data = [
            [
                'slug'     => '26/PWR/PHE/5001',
                'client'   => 'Khan Brothers Pvt.',
                'lab_id'   => $centralLabId,
                'date'     => '2026-03-08',
                'samples'  => 3,
                'total'    => 5400,
                'received' => 5400,
                'status'   => 'paid'
            ],
            [
                'slug'     => '26/PWR/PHE/5002',
                'client'   => 'WAPDA Colony',
                'lab_id'   => $centralLabId,
                'date'     => '2026-03-07',
                'samples'  => 5,
                'total'    => 9000,
                'received' => 4500,
                'status'   => 'partial'
            ],
            [
                'slug'     => '26/PWR/PHE/5003',
                'client'   => 'Al-Noor Hospital',
                'lab_id'   => $centralLabId,
                'date'     => '2026-03-06',
                'samples'  => 8,
                'total'    => 14400,
                'received' => 0,
                'status'   => 'pending'
            ],
            [
                'slug'     => 'C/26/PWR/C0012',
                'client'   => 'NESPAK Ltd.',
                'lab_id'   => $centralLabId,
                'date'     => '2026-03-05',
                'samples'  => 14,
                'total'    => 25200,
                'received' => 25200,
                'status'   => 'paid'
            ],
            [
                'slug'     => '26/PWR/PHE/5004',
                'client'   => 'NHA Office',
                'lab_id'   => $centralLabId,
                'date'     => '2026-03-04',
                'samples'  => 6,
                'total'    => 10800,
                'received' => 0,
                'status'   => 'pending'
            ],
            [
                'slug'     => '26/MRD/PHE/1091',
                'client'   => 'Mardan PHE WSS',
                'lab_id'   => $mardanLabId,
                'date'     => '2026-03-03',
                'samples'  => 4,
                'total'    => 7200,
                'received' => 7200,
                'status'   => 'paid'
            ]
        ];

        // Clean up previous seeded finance invoices to avoid duplicates
        DB::table('water_sample_invoice_logs')->delete();
        DB::table('water_sample_invoices')->delete();
        
        foreach ($data as $item) {
            // Get or create client
            $clientId = DB::table('clients')->where('name', $item['client'])->value('id');
            if (!$clientId) {
                $clientId = DB::table('clients')->insertGetId([
                    'name'         => $item['client'],
                    'type'         => 'private',
                    'email'        => 'contact@' . \Illuminate\Support\Str::slug($item['client']) . '.com',
                    'phone'        => '0300' . rand(1000000, 9999999),
                    'address'      => 'Peshawar',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Create 1 representative Sample for the invoice
            $sampleId = DB::table('water_samples')->insertGetId([
                'water_scheme_id'  => $schemeId,
                'laboratory_id'    => $item['lab_id'],
                'division_id'      => $divisionId,
                'province_id'      => $provinceId,
                'district_id'      => $districtId,
                'tehsil_id'        => $tehsilId,
                'slug'             => $item['slug'],
                'test_type'        => 'fresh',
                'source_type'      => 'gravity',
                'sampling_point'   => 'source',
                'collected_by'     => 'client',
                'collected_in'     => 'kit',
                'complaint'        => 'general_quality_analysis',
                'desired_test'     => 'Physical',
                'collectable_id'   => $clientId,
                'collectable_type' => Client::class,
                'created_by'       => $adminUser->id,
                'modified_by'      => $adminUser->id,
                'created_at'       => Carbon::parse($item['date']),
                'updated_at'       => Carbon::parse($item['date']),
            ]);

            // Create Invoice
            $invoiceId = DB::table('water_sample_invoices')->insertGetId([
                'water_sample_id'  => $sampleId,
                'invoiceable_id'   => $clientId,
                'invoiceable_type' => Client::class,
                'price'               => $item['total'],
                'discount_percentage' => 0,
                'net_amount'          => $item['total'],
                'paid'                => $item['received'],
                'balance'             => $item['total'] - $item['received'],
                'status'              => $item['status'],
                'created_by'          => $adminUser->id,
                'modified_by'         => $adminUser->id,
                'created_at'          => Carbon::parse($item['date']),
                'updated_at'          => Carbon::parse($item['date']),
            ]);

            // Create Payment logs if received > 0
            if ($item['received'] > 0) {
                $isSBP = str_contains($item['client'], 'WAPDA') || str_contains($item['client'], 'NHA');
                DB::table('water_sample_invoice_logs')->insert([
                    'water_sample_invoice_id' => $invoiceId,
                    'paid'                    => $item['received'],
                    'balance'                 => $item['total'] - $item['received'],
                    'payment_mode'            => $isSBP ? 'SBP' : 'Cash/Cheque',
                    'note'                    => $isSBP ? 'Direct deposit in State Bank' : 'Collected at counter',
                    'user_id'                 => $adminUser->id,
                    'created_at'              => Carbon::parse($item['date'])->addHours(2),
                    'updated_at'              => Carbon::parse($item['date'])->addHours(2),
                ]);
            }
        }

        $this->command->info('6 specific exact invoices seeded successfully!');
    }
}
