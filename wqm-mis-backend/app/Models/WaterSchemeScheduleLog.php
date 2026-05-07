<?php

namespace App\Models;

use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterSchemeScheduleLog extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'wss_schedule_id',
        'water_scheme_id',
        'laboratory_id',
        'scheduled_at',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water-scheme-schedule-log')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Scheme Schedule Log has been {$eventName}");
    }
}
