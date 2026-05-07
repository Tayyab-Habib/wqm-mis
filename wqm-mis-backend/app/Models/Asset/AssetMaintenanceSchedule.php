<?php

namespace App\Models\Asset;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Enums\StatusEnum;
use App\Models\AssetMaintenanceScheduleLog;
use App\Models\Scopes\LatestScope;
use App\Models\User;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMaintenanceSchedule extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'laboratory_asset_id',
        'type',
        'day_of_month',
        'frequency',
        'is_recurring',
        'status',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $casts = [
//        'status' => StatusEnum::class,
        'is_recurring' => 'bool',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('inventory_maintenance_schedules')
            ->setDescriptionForEvent(fn(string $eventName) => "Inventory Maintenance Schedule has been {$eventName}");
    }

    public function assetMaintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class);
    }

    public function laboratoryAsset(): BelongsTo
    {
        return $this->belongsTo(LaboratoryAsset::class);
    }

    public function asset(): HasOneThrough
    {
        return $this->hasOneThrough(Asset::class, LaboratoryAsset::class, 'asset_id', 'id', 'id', 'id');
    }

    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assetMaintenanceScheduleLog(): HasMany
    {
        return $this->hasMany(AssetMaintenanceScheduleLog::class, 'asset_ms_id');
    }

}
