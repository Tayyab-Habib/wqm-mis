<?php

namespace App\Console\Commands;

use App\Services\LaboratoryMaterialExpirationService;
use App\Services\NotifyExpiredMaterialService;
use Illuminate\Console\Command;

class LaboratoryMaterialExpirationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handle:laboratory-material-expiration-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alert the laboratory stakeholders about expired materials swiftly.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $today = now();
        (new LaboratoryMaterialExpirationService($today->startOfDay(), $today->endOfDay()));
        info('laboratory-material-expiration executed at: ' . now()->format('Y-m-d H:i:s'));
    }
}
