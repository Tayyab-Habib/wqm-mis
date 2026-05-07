<?php

namespace App\Models\Asset;

use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaboratoryAsset extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'laboratory_id',
        'asset_id',
        'quantity',
        'unit',
        'date_of_expiry',
        'status',
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
            ->useLogName('laboratory_inventories')
            ->setDescriptionForEvent(fn(string $eventName) => "Laboratory Inventory has been {$eventName}");
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function laboratoryAssetLogs(): HasMany
    {
        return $this->hasMany(LaboratoryAssetLog::class);
    }

    public function assetMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(AssetMaintenanceSchedule::class);
    }
}
