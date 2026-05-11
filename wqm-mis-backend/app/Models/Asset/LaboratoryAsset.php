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
        'make_model',
        'serial_number',
        'calibration_cycle',
        'next_calibration_date',
        'purchased_at',
        'warranty_expiry',
        'purchase_value',
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

    // Calibration/repair history was consolidated into asset_maintenance_logs
    // (see 2026_05_11_190000 migration). These relations now point at that
    // unified table, scoped by `type`. The two old EquipmentCalibrationLog /
    // EquipmentRepairLog models were deleted with the source tables.
    public function calibrationLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class)
            ->where('type', 'calibration')
            ->latest();
    }

    public function repairLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class)
            ->where('type', 'repair')
            ->latest();
    }
}
