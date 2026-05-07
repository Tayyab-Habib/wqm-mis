<?php

namespace App\Services;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Enums\StatusEnum;
use App\Models\Asset\AssetMaintenanceLog;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\AssetMaintenanceScheduleLog;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class AssetMaintenanceScheduleService
{
    public function notifyMaintenanceDueOneDayBefore(): void
    {
        $tomorrow = now()->addDay(1);
        $today = strtolower($tomorrow->format('l'));
        $dayOfMonth = $tomorrow->format('m-d');

        AssetMaintenanceSchedule::query()
            ->select([
                'asset_maintenance_schedules.id',
                'asset_maintenance_schedules.laboratory_asset_id',
                'day_of_month',
                'asset_maintenance_schedules.is_recurring',
                'assets.name',
                'laboratory_assets.laboratory_id',
            ])
            ->where('asset_maintenance_schedules.status', '=', StatusEnum::ACTIVE->value)
            ->whereIn('asset_maintenance_schedules.day_of_month', [$today, $dayOfMonth])
            ->leftJoin('laboratory_assets', 'asset_maintenance_schedules.laboratory_asset_id', '=', 'laboratory_assets.id')
            ->leftJoin('assets', 'laboratory_assets.asset_id', '=', 'assets.id')
            ->get()
            ->map(function ($schedule) use ($tomorrow) {
                $maintenanceScheduleExists = AssetMaintenanceLog::query()
                    ->where('asset_maintenance_schedule_id', '=', $schedule->id)
                    ->exists();

                if (!$schedule->is_recurring && !$maintenanceScheduleExists) {
                    $this->generateSchedule($schedule, $tomorrow);
                } elseif ($schedule->is_recurring) {
                    $this->generateSchedule($schedule, $tomorrow);
                }
            });
    }

    public function scheduleMaintenanceNotification(): void
    {
        AssetMaintenanceScheduleLog::query()
            ->select([
                'asset_maintenance_schedule_logs.laboratory_asset_id',
                'assets.name',
                'laboratory_assets.laboratory_id',
            ])
            ->leftJoin('laboratory_assets', 'asset_maintenance_schedule_logs.laboratory_asset_id', '=', 'laboratory_assets.id')
            ->leftJoin('assets', 'laboratory_assets.asset_id', '=', 'assets.id')
            ->where('scheduled_at', '=', now()->format('Y-m-d'))
            ->get()
            ->map(function ($schedule) {
                $user = $this->getLaboratorySystemManager($schedule->laboratory_id);
                $data = [
                    'content' => 'You have tomorrow maintenance schedule of ' . $schedule->name,
                    'name' => 'System Generated',
                ];
                Notification::send($user, new GenericNotification($data));
            });
    }

    public function generateSchedule($schedule, $tomorrow): void
    {
        DB::beginTransaction();
        $schedule->assetMaintenanceScheduleLog()
            ->create([
                'asset_ms_id' => $schedule->id,
                'laboratory_asset_id' => $schedule->laboratory_asset_id,
                'laboratory_id' => $schedule->laboratory_id,
                'scheduled_at' => $tomorrow->format('Y-m-d'),
                'status' => 'pending',
            ]);

        $user = $this->getLaboratorySystemManager($schedule->laboratory_id);

        $data = [
            'content' => 'You have tomorrow maintenance schedule of ' . $schedule->name,
            'name' => 'System Generated',
        ];

        Notification::send($user, new GenericNotification($data));

        DB::commit();
    }

    public function getLaboratorySystemManager($laboratoryId): Builder|Model
    {
        return User::query()
            ->whereHas('laboratoryUser', fn($query) => $query->where('laboratories.id', '=', $laboratoryId))
            ->whereHas('roles', fn($query) => $query->where('name', '=', 'system-manager'))
            ->first();
    }
}
