<?php

namespace App\Console\Commands;

use App\Services\MaterialExpirationService;
use Illuminate\Console\Command;

class MaterialExpirationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handle:material-expiration-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alert the system-administrator about expired materials swiftly.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = now();
        (new MaterialExpirationService($today->startOfDay(), $today->endOfDay()));
        info('material-expiration executed at: ' . now()->format('Y-m-d H:i:s'));
    }
}
