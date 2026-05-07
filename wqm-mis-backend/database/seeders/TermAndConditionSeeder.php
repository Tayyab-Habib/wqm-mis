<?php

namespace Database\Seeders;

use App\Models\TermAndCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TermAndConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $termAndConditions = [
            ['description' => 'The results of the laboratory analysis by PHED are verified as accurate and authentic only for the parameters tested.'],
            ['description' => 'Analysis report is not valid for court use or business purpose.'],
            ['description' => 'In case of any dispute in connection with authenticity of the report, the laboratory record of the analysis will be considered final.'],
            ['description' => 'PHED does not accept any responsibility regarding accuracy of sample collection procedures if collected by the client.'],
            ['description' => 'PHED will not be responsible for loss or damage to the samples in its possession for reasons beyond its control.'],
            ['description' => 'PHED reserves the right to accept or reject samples for analysis without assigning any reason.'],
        ];

        TermAndCondition::query()
            ->insert($termAndConditions);
    }
}
