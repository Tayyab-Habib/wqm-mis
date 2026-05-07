<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Region::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $regions = [
            'Chief Engineer (Center)',
            'Chief Engineer (East)',
            'Chief Engineer (North)',
            'Chief Engineer (South)',
        ];

        foreach ($regions as $name) {
            Region::create(['name' => $name]);
        }
    }
}
