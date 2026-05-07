<?php

namespace Database\Seeders;

use App\Models\Issues\IssueLog;
use Illuminate\Database\Seeder;

class IssueLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (0 === IssueLog::count()) {
            IssueLog::factory(10)
                ->create();
        }
    }
}
