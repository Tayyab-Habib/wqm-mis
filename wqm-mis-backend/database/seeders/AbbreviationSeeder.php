<?php

namespace Database\Seeders;

use App\Models\Abbreviation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AbbreviationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $abbreviations = [
            ['name' => 'NGVS', 'detail' => 'No Guideline Value Set'],
            ['name' => '+Ve/-Ve', 'detail' => 'Positive/Negative'],
            ['name' => 'E.C', 'detail' => 'Electrical Conductivity'],
            ['name' => 'TDS', 'detail' => 'Total Dissolved Solids'],
            ['name' => 'WHO', 'detail' => 'World Health Organization'],
            ['name' => 'NT', 'detail' => 'Not Tested'],
            ['name' => 'FNU', 'detail' => 'Formazin Nephelometric Units'],
            ['name' => 'BDL', 'detail' => 'Below Detection Limit'],
            ['name' => 'P', 'detail' => 'Physical'],
            ['name' => 'C', 'detail' => 'Chemical'],
            ['name' => 'M', 'detail' => 'Micro'],
            ['name' => 'μg/L', 'detail' => 'micro-gram per Liter'],
            ['name' => 'µS/cm', 'detail' => 'microsiemens / cm'],

        ];

        Abbreviation::query()->insert($abbreviations);

    }
}
