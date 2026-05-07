<?php

namespace Database\Seeders;

use App\Models\Material\Material;
use App\Models\Material\MaterialLog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Material::factory(100)
            ->has(MaterialLog::factory(rand(3, 10)))
            ->create();
    }
}
