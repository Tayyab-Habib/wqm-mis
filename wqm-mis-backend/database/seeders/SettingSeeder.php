<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $settings = [
            [
                'name' => 'PHE Invoice Discount',
                'description' => 'Add discount for PHE Water Sample Invoices',
                'value' => '50',
            ],
        ];
        Setting::query()
                ->insert($settings);
    }

}
