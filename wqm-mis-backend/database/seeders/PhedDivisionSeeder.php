<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\PhedDivision;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PhedDivisionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PhedDivision::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $data = [
            // CE Center – Peshawar Division
            'Khyber'           => ['Khyber'],
            'Mohmand'          => ['Mohmand'],
            'Charsadda'        => ['Charsadda'],
            'Nowshera'         => ['Nowshera'],
            'Peshawar'         => ['Peshawar-I', 'Peshawar-II'],

            // CE Center – Mardan Division
            'Mardan'           => ['Mardan'],
            'Swabi'            => ['Swabi'],

            // CE East – Hazara Division
            'Abbottabad'       => ['Abbottabad', 'BWS Abbottabad'],
            'Haripur'          => ['Haripur'],
            'Battagram'        => ['Battagram'],
            'Kohistan Lower'   => ['Kohistan Lower'],
            'Kohistan Upper'   => ['Kohistan Upper'],
            'Kolai Pallas'     => ['Kolai Pallas'],
            'Mansehra'         => ['Mansehra'],
            'Tor Ghar'         => ['Tor Ghar'],

            // CE North – Malakand Division
            'Chitral Lower'    => ['Chitral Lower'],
            'Chitral Upper'    => ['Chitral Upper'],
            'Dir Lower'        => ['Dir Lower'],
            'Dir Upper'        => ['Dir Upper'],
            'Bajaur'           => ['Bajaur'],
            'Buner'            => ['Buner'],
            'Malakand'         => ['Malakand'],
            'Shangla'          => ['Shangla'],
            'Swat'             => ['Swat-I', 'Swat-II'],

            // CE South – Bannu Division
            'Bannu'            => ['Bannu'],
            'Lakki Marwat'     => ['Lakki Marwat'],
            'North Waziristan' => ['North Waziristan'],

            // CE South – DI Khan Division
            'Dera Ismail Khan' => ['Dera Ismail Khan'],
            'South Waziristan' => ['South Waziristan'],
            'Tank'             => ['Tank'],

            // CE South – Kohat Division
            'Hangu'            => ['Hangu'],
            'Kurram'           => ['Kurram'],
            'Orakzai'          => ['Orakzai'],
            'Karak'            => ['Karak-I', 'Karak-II'],
            'Kohat'            => ['Kohat', 'BWS Kohat at Shakardara'],
        ];

        foreach ($data as $districtName => $divisions) {
            $district = District::where('name', $districtName)->first();
            if ($district) {
                foreach ($divisions as $divisionName) {
                    PhedDivision::create([
                        'district_id' => $district->id,
                        'circle_id'   => $district->circle_id,
                        'name'        => $divisionName,
                        'is_active'   => 1,
                    ]);
                }
            }
        }
    }
}
