<?php

namespace App\Console\Commands;

use App\Services\NotifyExpiredMaterialService;
use Illuminate\Console\Command;

class NotifyExpiredMaterialCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handle:notify-expired-materials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Alert the system-administrator about to be expired materials swiftly.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $tomorrow = now()->addDay();
        (new NotifyExpiredMaterialService($tomorrow->startOfDay(), $tomorrow->endOfDay()));
        info('notify-expired-materials executed at: ' . now()->format('Y-m-d H:i:s'));
    }
}
