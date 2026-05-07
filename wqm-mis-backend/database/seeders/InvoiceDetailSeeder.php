<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InvoiceDetailSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $invoiceIds = Invoice::query()->select('id')->pluck('id')->toArray();

        if (0 === InvoiceDetail::count()) {
            InvoiceDetail::factory(10)
                ->sequence(fn($sequence) => ['invoice_id' => $invoiceIds[array_rand($invoiceIds)]])
                ->create();
        }
    }
}
