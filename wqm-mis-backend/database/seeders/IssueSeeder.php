<?php

namespace Database\Seeders;

use App\Models\Issues\Issue;
use Illuminate\Database\Seeder;

class IssueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === Issue::count()) {
            Issue::factory(10)
                ->create();
        }
    }
}
