<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\HubLab;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CircleSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Circle::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $data = [
            'Central Lab Peshawar' => [
                'SE Khyber',
                'SE Peshawar',
            ],
            'Mardan Hub Lab' => [
                'SE Mardan',
            ],
            'Abbottabad Hub Lab' => [
                'SE Abbottabad',
                'SE Mansehra',
            ],
            'Batkhela Lab' => [
                'SE Malakand at Timergara',
            ],
            'Swat Hub Lab' => [
                'SE Swat',
            ],
            'Bannu Hub Lab' => [
                'SE Bannu',
            ],
            'DI Khan Hub Lab' => [
                'SE D.I Khan',
            ],
            'Kohat Hub Lab' => [
                'SE Hangu',
            ],
        ];

        foreach ($data as $labName => $circles) {
            $lab = HubLab::with('division')->where('name', $labName)->first();
            if ($lab && $lab->division) {
                foreach ($circles as $circleName) {
                    Circle::create([
                        'hub_lab_id' => $lab->id,
                        'region_id'  => $lab->division->region_id,
                        'name'       => $circleName,
                        'is_active'  => 1,
                    ]);
                }
            }
        }
    }
}
