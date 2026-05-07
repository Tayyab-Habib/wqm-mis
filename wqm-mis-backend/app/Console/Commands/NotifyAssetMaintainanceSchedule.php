<?php

namespace App\Console\Commands;

use App\Services\AssetMaintenanceScheduleService;
use Illuminate\Console\Command;

class NotifyAssetMaintainanceSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'handle:notify-asset-maintenance-schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications to the system manager about maintenance schedule of assets';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $assetMaintenanceScheduleService = (new AssetMaintenanceScheduleService());
        $assetMaintenanceScheduleService->notifyMaintenanceDueOneDayBefore();
        $assetMaintenanceScheduleService->scheduleMaintenanceNotification();
    }
}
