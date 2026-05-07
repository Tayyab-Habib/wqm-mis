<?php

namespace Database\Seeders;

trait PhedHierarchyData
{
    public function getPhedData()
    {
        return [
            'Chief Engineer (Center)' => [
                'SE Khyber' => ['Bajaur', 'Khyber', 'Mohmand'],
                'SE Mardan' => ['Mardan', 'Swabi'],
                'SE Peshawar' => ['Charsadda', 'Nowshera', 'Peshawar-I', 'Peshawar-II'],
            ],
            'Chief Engineer (East)' => [
                'SE Abbottabad' => ['Abbottabad', 'BWS Abbottabad', 'Haripur'],
                'SE Mansehra' => ['Battagram', 'Kohistan Lower', 'Kohistan Upper', 'Kolai Pallas', 'Mansehra', 'Tor Ghar'],
            ],
            'Chief Engineer (North)' => [
                'SE Malakand at Timergara' => ['Chitral Lower', 'Chitral Upper', 'Dir Lower', 'Dir Upper'],
                'SE Swat' => ['Buner', 'Malakand', 'Shangla', 'Swat-I', 'Swat-II'],
            ],
            'Chief Engineer (South)' => [
                'SE Bannu' => ['Bannu', 'Karak-II', 'Lakki Marwat', 'North Waziristan'],
                'SE D.I Khan' => ['Dera Ismail Khan', 'South Waziristan', 'Tank'],
                'SE Hangu' => ['Hangu', 'Kurram', 'Orakzai'],
                'SE Kohat' => ['BWS Kohat at Shakardara', 'Karak-I', 'Kohat'],
            ],
        ];
    }
}
