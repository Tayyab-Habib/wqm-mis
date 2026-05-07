<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\Region;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DivisionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Division::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $province = Province::where('name', 'Khyber Pakhtunkhwa')->first();
        $provinceId = $province ? $province->id : 1;

        $data = [
            'Chief Engineer (Center)' => ['Peshawar', 'Mardan'],
            'Chief Engineer (East)'   => ['Abbottabad'],
            'Chief Engineer (North)'  => ['Malakand'],
            'Chief Engineer (South)'  => ['Kohat', 'Bannu', 'D.I. Khan'],
        ];

        foreach ($data as $regionName => $divisions) {
            $region = Region::where('name', $regionName)->first();
            if ($region) {
                foreach ($divisions as $divisionName) {
                    Division::create([
                        'region_id'    => $region->id,
                        'province_id'  => $provinceId,
                        'name'         => $divisionName,
                        'abbreviation' => strtoupper(substr($divisionName, 0, 3)),
                    ]);
                }
            }
        }
    }
}
