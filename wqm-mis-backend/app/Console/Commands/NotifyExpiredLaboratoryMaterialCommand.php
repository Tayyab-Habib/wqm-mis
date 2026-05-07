<?php

namespace App\Console\Commands;

use App\Services\NotifyExpiredLaboratoryMaterialService;
use Illuminate\Console\Command;

class NotifyExpiredLaboratoryMaterialCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handle:notify-expired-laboratory-materials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alert the laboratory stakeholders about to be expired materials swiftly.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tomorrow = now()->addDay();
        (new NotifyExpiredLaboratoryMaterialService($tomorrow->startOfDay(), $tomorrow->endOfDay()));
        info('notify-expired-laboratory-materials executed at: ' . now()->format('Y-m-d H:i:s'));
    }
}
