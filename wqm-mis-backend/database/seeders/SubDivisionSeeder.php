<?php

namespace Database\Seeders;

use App\Models\PhedDivision;
use App\Models\SubDivision;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubDivisionSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SubDivision::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $data = [
            // CE Center – SE Khyber
            'Khyber'                  => ['Bara', 'Jamrud'],
            'Mohmand'                 => ['Yakaghund', 'Ghalanai'],

            // CE Center – SE Peshawar
            'Charsadda'               => ['Tangi', 'Charsadda'],
            'Nowshera'                => ['No.1 Nowshera', 'No.2 Nowshera'],
            'Peshawar-I'              => ['No.1 Peshawar', 'No.2 Peshawar'],
            'Peshawar-II'             => ['Hassan Khel', 'Pishtakhara'],

            // CE Center – SE Mardan
            'Mardan'                  => ['Takht Bhai Mardan', 'Mardan'],
            'Swabi'                   => ['Lahor', 'Swabi'],

            // CE East – SE Abbottabad
            'Abbottabad'              => ['Sub Division-I Abbottabad', 'Sub Division-II Abbottabad'],
            'BWS Abbottabad'          => ['BWS Abbottabad'],
            'Haripur'                 => ['Ghazi Haripur', 'Haripur'],

            // CE East – SE Mansehra
            'Battagram'               => ['Battagram', 'Allai Battagram'],
            'Kohistan Lower'          => ['Pattan Kohistan'],
            'Kohistan Upper'          => ['Dassu Kohistan'],
            'Kolai Pallas'            => ['Kolai Palas Kohistan'],
            'Mansehra'                => ['Mansehra', 'Oghi Mansehra', 'Balakot Mansehra'],
            'Tor Ghar'                => ['Tor Ghar'],

            // CE North – SE Malakand at Timergara
            'Chitral Lower'           => ['Drosh Chitral', 'Chitral'],
            'Chitral Upper'           => ['Mastuj', 'Booni'],
            'Dir Lower'               => ['Chakdara Dir Lower', 'Samar Bagh Dir Lower', 'Timergara, Dir Lower'],
            'Dir Upper'               => ['Sheringal Dir Upper', 'Dir Upper', 'Warai Dir Upper'],
            'Bajaur'                  => ['Khar Bajaur', 'Nawagai Bajaur'],

            // CE North – SE Swat
            'Buner'                   => ['Totalai Buner', 'Daggar No.1 Buner', 'Daggar No.2 Buner'],
            'Malakand'                => ['Batkhela Malakand', 'Dargai Malakand'],
            'Shangla'                 => ['Puran Shangla', 'Alpuri Shangla'],
            'Swat-I'                  => ['Saidu-I Swat', 'Saidu-II Swat'],
            'Swat-II'                 => ['Khwazakhela Swat', 'Matta-II Swat-II', 'Matta-I Swat-II'],

            // CE South – SE Bannu
            'Bannu'                   => ['No.2 Bannu', 'No.1 Bannu'],
            'Lakki Marwat'            => ['Naurang Lakki Marwat', 'Lakki Marwat-I'],
            'North Waziristan'        => ['Ladda', 'Wana'],

            // CE South – SE D.I Khan
            'Dera Ismail Khan'        => ['Kulachi', 'Paharpur', 'D.I. Khan'],
            'South Waziristan'        => ['Miranshah', 'Mirali'],
            'Tank'                    => ['No.1 Tank', 'No.2 Tank'],

            // CE South – SE Hangu
            'Hangu'                   => ['Thall Hangu', 'Hangu'],
            'Kurram'                  => ['Upper Kurram', 'Lower Kurram'],
            'Orakzai'          => ['Kalaya', 'Ghiljo'],
            'Karak-I'                 => ['BD Shah', 'Karak-I'],
            'Karak-II'                => ['Takht-e-Nasratti-I Karak', 'Takht-e-Nasratti-II Karak'],
            'Kohat'                   => ['Kohat', 'Lachi Kohat'],
            'BWS Kohat at Shakardara' => ['Bulk WS Shakardara Kohat'],
        ];

        foreach ($data as $divisionName => $subDivisions) {
            $division = PhedDivision::where('name', $divisionName)->first();
            if ($division) {
                foreach ($subDivisions as $subName) {
                    SubDivision::create([
                        'phed_division_id' => $division->id,
                        'name'             => $subName,
                    ]);
                }
            }
        }
    }
}
