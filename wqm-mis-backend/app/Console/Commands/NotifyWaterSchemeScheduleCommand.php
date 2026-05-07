<?php

namespace App\Console\Commands;

use App\Services\WaterSchemeScheduleService;
use Illuminate\Console\Command;

class NotifyWaterSchemeScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handle:notify-water-scheme-schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications to the system manager about schedule of water schemes';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $assetMaintenanceScheduleService = (new WaterSchemeScheduleService());
        $assetMaintenanceScheduleService->notifyScheduleDueOneDayBefore();
        $assetMaintenanceScheduleService->scheduleWaterSchemeNotification();
    }
}
