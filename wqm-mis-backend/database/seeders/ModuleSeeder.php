<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $modules = [
            ['name' => 'inventories'],
            ['name' => 'stocks'],
            ['name' => 'issues'],
            ['name' => 'complaints'],
            ['name' => 'laboratories'],
            ['name' => 'water_parameters'],
            ['name' => 'water_schemes'],
            ['name' => 'provinces'],
            ['name' => 'divisions'],
            ['name' => 'districts'],
            ['name' => 'tehsils'],
            ['name' => 'union_councils'],
            ['name' => 'water_samples'],
            ['name' => 'abbreviations'],
            ['name' => 'settings'],
            ['name' => 'invoices'],
            ['name' => 'inventories'],
            ['name' => 'payments'],
            ['name' => 'purchase_orders'],
            ['name' => 'term_and_conditions'],
            ['name' => 'users'],
            ['name' => 'clients'],
            ['name' => 'designations'],
            ['name' => 'roles'],
            ['name' => 'permissions'],
            ['name' => 'units'],
            ['name' => 'handing_takings'],
            ['name' => 'reports'],
            ['name' => 'folders'],
            ['name' => 'diaries'],
            ['name' => 'dispatches'],
        ];

        if (0 === Module::query()->count()) {
            Module::query()->insert($modules);
        }
    }
}
