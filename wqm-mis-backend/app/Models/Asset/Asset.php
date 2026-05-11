<?php

namespace App\Models\Asset;

use App\Enums\AssetStatusEnum;
use App\Models\Inventory\Inventory;
use App\Models\Payment;
use App\Models\Scopes\LatestScope;
use App\Traits\IsActiveScopeTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait, IsActiveScopeTrait;

    protected $fillable = [
        'name',
        'kind',
        'category',
        'item_code',
        'quantity',
        'unit',
        'date_of_expiry',
        'status',
        'condition',
        'date_of_purchase',
        'purchase_value',
        'location',
        'last_verified',
        'remarks',
        'is_active',
        'specification',
        'country',
        'agency',
    ];

    protected $hidden = [
        'deleted_at'
    ];


    protected $casts = [
        'status' => AssetStatusEnum::class,
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
            ->useLogName('inventories')
            ->setDescriptionForEvent(fn(string $eventName) => "Inventory has been {$eventName}");
    }

    public function assetLogs(): HasMany
    {
        return $this->hasMany(AssetLog::class);
    }

    public function laboratoryAssets(): HasMany
    {
        return $this->hasMany(LaboratoryAsset::class);
    }

    public function inventory()
    {
        return $this->morphMany(Inventory::class, 'inventoryable');
    }

}
