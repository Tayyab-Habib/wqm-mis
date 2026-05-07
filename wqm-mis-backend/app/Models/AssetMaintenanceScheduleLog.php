<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMaintenanceScheduleLog extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'asset_ms_id',
        'laboratory_asset_id',
        'laboratory_id',
        'scheduled_at',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('asset-maintenance-schedule-log')
            ->setDescriptionForEvent(fn(string $eventName) => "Asset Maintenance Schedule Log has been {$eventName}");
    }
}
