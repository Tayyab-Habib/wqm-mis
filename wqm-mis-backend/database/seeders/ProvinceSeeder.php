<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Division;
use App\Models\Province;
use App\Models\Tehsil;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProvinceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $provinces = [
            [
                'province' => 'Khyber Pakhtunkhwa',
                'divisions' => [
                    [
                        'division' => 'Peshawar',
                        'abbreviation' => 'PWR',
                        'districts' => [
                            [
                                'district' => 'Peshawar',
                                'tehsils' => [
                                    'Peshawar Town-I',
                                    'Peshawar Town-II',
                                    'Peshawar Town-III',
                                    'Peshawar Town-IV',
                                    'Badhber',
                                    'Chamkani',
                                    'Koh-e-Daman',
                                    'Pishtakhara',
                                    'Shah Alam',
                                    'Mathra',
                                ],
                            ],
                            //],
                            [
                                'district' => 'Charsadda',
                                'tehsils' => [
                                    'Charsadda',
                                    'Tangi',
                                    'Shabqadar',
                                ],
                            ],
                            [
                                'district' => 'Nowshera',
                                'tehsils' => [
                                    'Nowshera',
                                    'Jehangira',
                                    'Pabbi',
                                ],
                            ],
                            [
                                'district' => 'Mohmand',
                                'tehsils' => [
                                    'Upper Mohmand',
                                    'Lower Mohmand',
                                    'Baizai'
                                ],
                            ],
                            [
                                'district' => 'Khyber',
                                'tehsils' => [
                                    'Bara',
                                    'Jamrud',
                                    'Landikotal',
                                ]
                            ],
                        ],
                    ],
                    [
                        'division' => 'Mardan',
                        'abbreviation' => 'MDN',
                        'districts' => [
                            [
                                'district' => 'Mardan',
                                'tehsils' => [
                                    'Mardan City',
                                    'Mardan',
                                    'Katlang',
                                    'Rustam',
                                    'Takht Bhai',
                                    'Garhi Kapoora',
                                ]
                            ],
                            [
                                'district' => 'Swabi',
                                'tehsils' => [
                                    'Swabi',
                                    'Topi',
                                    'Lahor',
                                    'Razzar',
                                ],
                            ],
                        ],
                    ],
                    [
                        'division' => 'Malakand',
                        'abbreviation' => 'MLK',
                        'districts' => [
                            [
                                'district' => 'Upper Chitral',
                                'tehsils' => [
                                    'Mustuj',
                                    'Mulkhow Torkhow',
                                ]
                            ],
                            [
                                'district' => 'Lower Chitral',
                                'tehsils' => [
                                    'Chitral',
                                    'Drosh',
                                ]
                            ],
                            [
                                'district' => 'Upper Dir',
                                'tehsils' => [
                                    'Dir Upper',
                                    'Shringal',
                                    'Wari',
                                    'Larjum',
                                    'Kalkot',
                                    'Barawal',
                                ]
                            ],
                            [
                                'district' => 'Lower Dir',
                                'tehsils' => [
                                    'Timergara',
                                    'Adenzai',
                                    'Lal Qilla',
                                    'Khall',
                                    'Balambat',
                                    'Munda',
                                    'Samarbagh',
                                ]
                            ],
                            [
                                'district' => 'Swat',
                                'tehsils' => [
                                    'Babuzai',
                                    'Barikot',
                                    'Kabal',
                                    'Matta Shamozi',
                                    'Khawaza Khela',
                                    'Charbagh',
                                    'Behrain',
                                ]
                            ],
                            [
                                'district' => 'Shangla',
                                'tehsils' => [
                                    'Alpuri',
                                    'Puran',
                                    'Besham',
                                    'Martung',
                                    'Chakisar',
                                ]
                            ],
                            [
                                'district' => 'Buner',
                                'tehsils' => [
                                    'Daggar',
                                    'Gagra',
                                    'Totali (Mandan)',
                                    'Khadokhel',
                                ]
                            ],
                            [
                                'district' => 'Malakand',
                                'tehsils' => [
                                    'Batkhela',
                                    'Dargai',
                                    'Thana Baizai',
                                ]
                            ],
                            [
                                'district' => 'Bajaur',
                                'tehsils' => [
                                    'Khar',
                                    'Nawagai',
                                ]
                            ],
                        ],
                    ],
                    [
                        'division' => 'Abbottabad',
                        'abbreviation' => 'ABD',
                        'districts' => [
                            [
                                'district' => 'Kohistan Lower',
                                'tehsils' => [
                                    'Pattan',
                                    'Bankand / Ranulia',
                                ]
                            ],
                            [
                                'district' => 'Kohistan',
                                'tehsils' => [
                                    'Dassu',
                                    'Kandia',
                                    'Harban Basha',
                                ]
                            ],
                            [
                                'district' => 'Kolai Palas Kohistan',
                                'tehsils' => [
                                    'Palas',
                                    'Battaira Kolai',
                                ]
                            ],
                            [
                                'district' => 'Mansehra',
                                'tehsils' => [
                                    'Mansehra',
                                    'Ballakot',
                                    'Ogi',
                                    'Baffa Pakhal',
                                    'Darbund',
                                ]
                            ],
                            [
                                'district' => 'Torghar',
                                'tehsils' => [
                                    'Judbah',
                                    'Hassanzai',
                                    'Dour Mera',
                                ]
                            ],
                            [
                                'district' => 'Battagram',
                                'tehsils' => [
                                    'Battagram',
                                    'Allai',
                                ]

                            ],
                            [
                                'district' => 'Abbottabad',
                                'tehsils' => [
                                    'Abbottabad City',
                                    'Havallian',
                                    'Abbottabad',
                                    'Lora',
                                    'Lower Tanawal',
                                ]
                            ],
                            [
                                'district' => 'Haripur',
                                'tehsils' => [
                                    'Haripur',
                                    'Ghazi',
                                    'Khanpur',
                                ]
                            ],
                        ],
                    ],
                    [
                        'division' => 'Kohat',
                        'abbreviation' => 'KHT',
                        'districts' => [
                            [
                                'district' => 'Kohat',
                                'tehsils' => [
                                    'Kohat City',
                                    'Kohat',
                                    'Darra Adam Khel',
                                    'Lachi',
                                    'Gumbat',
                                ]
                            ],
                            [
                                'district' => 'Karak',
                                'tehsils' => [
                                    'Karak',
                                    'Banda Daud Shah',
                                    'Takht-e-Nusrati',
                                ]
                            ],
                            [
                                'district' => 'Hangu',
                                'tehsils' => [
                                    'Hangu',
                                    'Thall',
                                ]
                            ],
                            [
                                'district' => 'Orakzai',
                                'tehsils' => [
                                    'Upper Orakzai',
                                    'Lower Orakzai',
                                ]
                            ],
                            [
                                'district' => 'Kurram',
                                'tehsils' => [
                                    'Upper Kurram',
                                    'Lower Kurram',
                                    'Lower Kurram',
                                ]
                            ],
                        ],
                    ],
                    [
                        'division' => 'Bannu',
                        'abbreviation' => 'BNU',
                        'districts' => [
                            [
                                'district' => 'Bannu',
                                'tehsils' => [
                                    'Bannu City',
                                    'Bannu',
                                    'Domel',
                                    'Wazir',
                                    'Meryan',
                                    'Baka Khel',
                                    'Kakki',
                                ]
                            ],
                            [
                                'district' => 'Lakki Marwat',
                                'tehsils' => [
                                    'Lakki Marwat',
                                    'Sari Naurang',
                                    'Battani',
                                    'Ghazni Khel',
                                ]
                            ],
                            [
                                'district' => 'North Waziristan',
                                'tehsils' => [
                                    'Miran Shah',
                                    'Razmak',
                                    'Mirali',
                                ]
                            ],
                        ],
                    ],
                    [
                        'division' => 'D.I. Khan',
                        'abbreviation' => 'DIK',
                        'districts' => [
                            [
                                'district' => 'D.I. Khan',
                                'tehsils' => [
                                    'D.I. Khan City',
                                    'D.I. Khan',
                                    'Pahar pur',
                                    'Paroa',
                                    'Kulachi',
                                    'Daraban (Kala)',
                                    'Drazanda',
                                ],
                            ],
                            [
                                'district' => 'Tank',
                                'tehsils' => [
                                    'Tank',
                                    'Jandola',
                                ]
                            ],
                            [
                                'district' => 'South Waziristan',
                                'tehsils' => [
                                    'Wana',
                                    'Ladha',
                                    'Sarwakai',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            DB::beginTransaction();
            foreach ($provinces as $province) {
                $provinceId = Province::query()->insertGetId([
                    'name' => $province['province'],
                    'logo' => '/logos/default-logo.png'
                ]);

                foreach ($province['divisions'] as $divisions) {
                    $divisionId = Division::query()->insertGetId([
                        'name' => $divisions['division'],
                        'abbreviation' => $divisions['abbreviation'],
                        'province_id' => $provinceId,
                    ]);

                    foreach ($divisions['districts'] as $districts) {
                        $districtId = District::query()->insertGetId([
                            'name' => $districts['district'],
                            'division_id' => $divisionId,
                        ]);

                        foreach ($districts['tehsils'] as $tehsil) {
                            Tehsil::query()->insertGetId([
                                'name' => $tehsil,
                                'district_id' => $districtId,
                            ]);
                        }
                    }
                }
            }
            DB::commit();
        } catch (\Exception $exception) {
            Log::error($exception->getMessage());
            DB::rollBack();
        }
    }
}
