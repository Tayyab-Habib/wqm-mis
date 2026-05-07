<?php

namespace App\Models\Asset;

use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaboratoryAssetLog extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'laboratory_asset_id',
        'asset_log_id',
        'quantity',
        'unit',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('laboratory_inventory_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Laboratory Inventory Log has been {$eventName}");
    }

    public function LaboratoryAsset(): BelongsTo
    {
        return $this->belongsTo(LaboratoryAsset::class);
    }

    public function assetLog(): BelongsTo
    {
        return $this->belongsTo(AssetLog::class);
    }
}
