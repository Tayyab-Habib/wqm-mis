<?php

namespace Database\Seeders;

use App\Models\Circle;
use App\Models\District;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistrictSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        District::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $data = [
            'SE Khyber' => [
                'Khyber',
                'Mohmand',
            ],
            'SE Peshawar' => [
                'Charsadda',
                'Nowshera',
                'Peshawar',
            ],
            'SE Mardan' => [
                'Mardan',
                'Swabi',
            ],
            'SE Abbottabad' => [
                'Abbottabad',
                'Haripur',
            ],
            'SE Mansehra' => [
                'Battagram',
                'Kohistan Lower',
                'Kohistan Upper',
                'Kolai Pallas',
                'Mansehra',
                'Tor Ghar',
            ],
            'SE Malakand at Timergara' => [
                'Chitral Lower',
                'Chitral Upper',
                'Dir Lower',
                'Dir Upper',
                'Bajaur',
            ],
            'SE Swat' => [
                'Buner',
                'Malakand',
                'Shangla',
                'Swat',
            ],
            'SE Bannu' => [
                'Bannu',
                'Lakki Marwat',
                'North Waziristan',
            ],
            'SE D.I Khan' => [
                'Dera Ismail Khan',
                'South Waziristan',
                'Tank',
            ],
            'SE Hangu' => [
                'Hangu',
                'Kurram',
                'Orakzai',
                'Karak',
                'Kohat',
            ],
        ];

        foreach ($data as $circleName => $districts) {
            $circle = Circle::with('hubLab.division')->where('name', $circleName)->first();
            if ($circle && $circle->hubLab && $circle->hubLab->division) {
                foreach ($districts as $districtName) {
                    District::create([
                        'circle_id'   => $circle->id,
                        'division_id' => $circle->hubLab->division->id,
                        'name'        => $districtName,
                    ]);
                }
            }
        }

        $districtsPositions = [
            ['name' => 'Kolai Palas', 'latitude' => 35.127161, 'longitude' => 72.861683],
            ['name' => 'Mansehra', 'latitude' => 34.328018, 'longitude' => 73.199099],
            ['name' => 'Torghar', 'latitude' => 34.581210, 'longitude' => 72.860407],
            ['name' => 'Battagram', 'latitude' => 34.673606, 'longitude' => 73.024567],
            ['name' => 'Abbottabad', 'latitude' => 34.176729, 'longitude' => 73.238344],
            ['name' => 'Haripur', 'latitude' => 34.002355, 'longitude' => 72.920050],
            ['name' => 'Kohistan', 'latitude' => 35.224266, 'longitude' => 73.173699],
            ['name' => 'Kohistan Lower', 'latitude' => 35.280307, 'longitude' => 73.287644],
            ['name' => 'Bannu', 'latitude' => 32.981500, 'longitude' => 70.642722],
            ['name' => 'Lakki Marwat', 'latitude' => 32.608491, 'longitude' => 70.903578],
            ['name' => 'North Waziristan', 'latitude' => 32.955612, 'longitude' => 70.021169],
            ['name' => 'D I Khan', 'latitude' => 31.860115, 'longitude' => 70.895022],
            ['name' => 'Tank', 'latitude' => 32.131196, 'longitude' => 70.391294],
            ['name' => 'South Waziristan', 'latitude' => 32.377058, 'longitude' => 69.684810],
            ['name' => 'Hangu', 'latitude' => 33.526416, 'longitude' => 71.062721],
            ['name' => 'Kohat', 'latitude' => 33.565969, 'longitude' => 71.466163],
            ['name' => 'Karak', 'latitude' => 33.109200, 'longitude' => 71.085107],
            ['name' => 'Orakzai', 'latitude' => 33.711839, 'longitude' => 70.972568],
            ['name' => 'Kurram', 'latitude' => 33.731894, 'longitude' => 70.354532],
            ['name' => 'Dir Upper', 'latitude' => 35.324494, 'longitude' => 72.066559],
            ['name' => 'Upper Chitral', 'latitude' => 35.795956, 'longitude' => 71.780442],
            ['name' => 'Lower Chitral', 'latitude' => 35.759200, 'longitude' => 71.772795],
            ['name' => 'Lower Dir', 'latitude' => 34.783749, 'longitude' => 71.944299],
            ['name' => 'Swat', 'latitude' => 34.803489, 'longitude' => 72.380958],
            ['name' => 'Shangla', 'latitude' => 34.887722, 'longitude' => 72.600409],
            ['name' => 'Buner', 'latitude' => 34.466389, 'longitude' => 72.527267],
            ['name' => 'Malakand', 'latitude' => 34.537060, 'longitude' => 71.919753],
            ['name' => 'Bajaur', 'latitude' => 34.777624, 'longitude' => 71.510932],
            ['name' => 'Mardan', 'latitude' => 34.190683, 'longitude' => 72.044725],
            ['name' => 'Swabi', 'latitude' => 34.131571, 'longitude' => 72.458869],
            ['name' => 'Charsadda', 'latitude' => 34.163392, 'longitude' => 71.753588],
            ['name' => 'Nowshera', 'latitude' => 34.003694, 'longitude' => 71.996745],
            ['name' => 'Mohmand', 'latitude' => 34.478871, 'longitude' => 71.365589],
            ['name' => 'Khyber', 'latitude' => 33.973494, 'longitude' => 71.139025],
            ['name' => 'Peshawar', 'latitude' => 33.998413, 'longitude' => 71.562856],
        ];

        foreach ($districtsPositions as $pos) {
            District::where('name', $pos['name'])
                ->update(['latitude' => $pos['latitude'], 'longitude' => $pos['longitude']]);
        }
    }
}
