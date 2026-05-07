<?php

namespace Database\Seeders;

use App\Models\ComplaintType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ComplaintTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === ComplaintType::count()) {
            $complaintTypes = [
                ['name' => 'Technical'],
                ['name' => 'Logistic'],
                ['name' => 'HR'],
            ];

            ComplaintType::query()->insert($complaintTypes);
        }
    }
}
