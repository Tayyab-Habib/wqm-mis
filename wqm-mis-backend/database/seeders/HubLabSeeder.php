<?php

namespace Database\Seeders;

use App\Models\Division;
use App\Models\HubLab;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HubLabSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        HubLab::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $data = [
            'Peshawar'  => 'Central Lab Peshawar',
            'Mardan'    => 'Mardan Hub Lab',
            'Abbottabad' => 'Abbottabad Hub Lab',
            'Malakand'  => 'Batkhela Lab',
            'Bannu'     => 'Bannu Hub Lab',
            'D.I. Khan' => 'DI Khan Hub Lab',
            'Kohat'     => 'Kohat Hub Lab',
        ];

        foreach ($data as $divisionName => $labName) {
            $division = Division::where('name', $divisionName)->first();
            if ($division) {
                HubLab::create([
                    'division_id' => $division->id,
                    'name'        => $labName,
                ]);
            }
        }

        // Malakand division has a second hub lab (Swat Hub Lab)
        $malakand = Division::where('name', 'Malakand')->first();
        if ($malakand) {
            HubLab::create([
                'division_id' => $malakand->id,
                'name'        => 'Swat Hub Lab',
            ]);
        }
    }
}
