<?php

namespace Database\Seeders;

use App\Models\DiaryDispatch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiaryDispatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === DiaryDispatch::count()) {
            DiaryDispatch::factory(10)
                ->create();
        }
    }
}
