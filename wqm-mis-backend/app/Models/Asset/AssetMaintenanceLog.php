<?php

namespace App\Models\Asset;

use App\Enums\AssetMaintenanceStatusEnum;
use App\Models\Scopes\LatestScope;
use App\Models\User;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMaintenanceLog extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'user_id',
        'asset_maintenance_schedule_id',
        'laboratory_asset_id',
        'type',                 // 'calibration' | 'repair'
        'event_date',           // calibration_date OR fault_date
        'result',               // Pass/Fail/Conditional Pass OR Resolved/Beyond Repair/etc.
        'next_due_date',        // calibration only
        'description',          // fault_description (repair)
        'reported_by',          // repair only
        'resolved_date',        // repair only
        'cost',                 // repair_cost
        'performer',            // calibrated_by OR technician
        'ref_number',           // certificate_ref (calibration)
        'standard_used',        // calibration standard
        'status',
        'comment',
        'file',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    // `status` previously used AssetMaintenanceStatusEnum, but now also holds
    // generic strings like 'completed' from the calibration/repair migration.
    // Casting to the enum here would break those rows, so leave it as a string.
    protected $casts = [];

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('inventory_maintenance_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Inventory Maintenance Log has been {$eventName}");
    }

    public function file(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url(Storage::url($value)) : null,
        );
    }

    public function assetMaintenanceSchedule(): BelongsTo
    {
        return $this->belongsTo(AssetMaintenanceSchedule::class);
    }

    public function laboratoryAsset(): BelongsTo
    {
        return $this->belongsTo(LaboratoryAsset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
