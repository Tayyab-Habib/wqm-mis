<?php

namespace Database\Seeders;

use App\Models\HandingTaking;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class HandingTakingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === HandingTaking::count()) {
            HandingTaking::factory(10)->create();
        }
    }
}
