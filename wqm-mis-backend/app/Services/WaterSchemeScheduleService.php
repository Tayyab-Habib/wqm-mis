<?php

namespace App\Services;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Enums\StatusEnum;
use App\Models\Asset\AssetMaintenanceLog;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\AssetMaintenanceScheduleLog;
use App\Models\User;
use App\Models\WaterSchemeSchedule;
use App\Models\WaterSchemeScheduleLog;
use App\Notifications\GenericNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class WaterSchemeScheduleService
{
    public function notifyScheduleDueOneDayBefore(): void
    {
        $tomorrow = now()->addDay(1);
        $todayInWords = strtolower($tomorrow->format('l'));
        $todayInNumber = strtolower($tomorrow->format('d'));
        $dayOfMonth = $tomorrow->format('m-d');

        WaterSchemeSchedule::query()
            ->select([
                'water_scheme_schedules.id',
                'water_scheme_schedules.day_of_month',
                'water_scheme_schedules.water_scheme_id',
                'water_scheme_schedules.is_recurring',
                'water_scheme_schedules.laboratory_id',
                'water_schemes.name as water_scheme_name',
                'water_scheme_schedules.created_by',
            ])
            ->leftJoin('water_schemes', 'water_scheme_schedules.water_scheme_id', '=', 'water_schemes.id')
            ->where('water_scheme_schedules.status', '=', StatusEnum::ACTIVE->value)
            ->whereIn('water_scheme_schedules.day_of_month', [$todayInNumber, $todayInWords, $dayOfMonth])
            ->get()
            ->map(function ($schedule) use ($tomorrow) {
                $maintenanceScheduleExists = WaterSchemeScheduleLog::query()
                    ->where('wss_schedule_id', '=', $schedule->id)
                    ->exists();

                if (!$schedule->is_recurring && !$maintenanceScheduleExists) {
                    $this->generateSchedule($schedule, $tomorrow);
                } elseif ($schedule->is_recurring) {
                    $this->generateSchedule($schedule, $tomorrow);
                }
            });
    }

    public function scheduleWaterSchemeNotification(): void
    {
        WaterSchemeScheduleLog::query()
            ->select([
                'water_scheme_schedule_logs.id',
                'water_scheme_schedule_logs.laboratory_id',
                'water_schemes.name as water_scheme_name',
                'water_scheme_schedules.created_by as created_by'
            ])
            ->leftJoin('water_schemes', 'water_scheme_schedule_logs.water_scheme_id', '=', 'water_schemes.id')
            ->leftJoin('water_scheme_schedules', 'water_scheme_schedule_logs.wss_schedule_id', '=', 'water_scheme_schedules.id')
            ->where('scheduled_at', '=', now()->format('Y-m-d'))
            ->get()
            ->map(function ($schedule) {
                $user = $this->getUser($schedule->created_by);
                $data = [
                    'content' => 'You have today testing schedule of ' . $schedule->water_scheme_name,
                    'name' => 'System Generated',
                ];
                Notification::send($user, new GenericNotification($data));
            });
    }

    public function generateSchedule($schedule, $tomorrow): void
    {
        DB::beginTransaction();
        $schedule->waterSchemeScheduleLogs()
            ->create([
                'wss_schedule_id' => $schedule->id,
                'water_scheme_id' => $schedule->water_scheme_id,
                'laboratory_id'=> $schedule->laboratory_id,
                'scheduled_at' => $tomorrow->format('Y-m-d'),
                'status' => 'pending',
            ]);

        $user = $this->getUser($schedule->created_by);

        $data = [
            'content' => 'You have tomorrow testing schedule of ' . $schedule->name,
            'name' => 'System Generated',
        ];

        Notification::send($user, new GenericNotification($data));

        DB::commit();
    }

    public function getUser($userId): Builder|Model
    {
        return User::query()
            ->find($userId);
    }
}
