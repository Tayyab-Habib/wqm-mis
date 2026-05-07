<?php

namespace App\Models\Inventory;

use App\Enums\InventoryDetailStatusEnum;
use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InventoryDetail extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'inventory_id',
        'inventoryable_type',
        'inventoryable_id',
        'quantity',
        'approved_quantity',
        'unit',
        'status',
        'is_received',
        'received_at',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => InventoryDetailStatusEnum::class,
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
            ->useLogName('inventory_request_details')
            ->setDescriptionForEvent(fn(string $eventName) => "Inventory Request Detail has been {$eventName}");
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * Get the parent inventoryable model (assets or material).
     */
    public function inventoryable()
    {
        return $this->morphTo();
    }

    public function inventoryLogs(): hasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function latestInventoryLog(): HasOne
    {
        return $this->hasOne(InventoryLog::class)->latestOfMany();
    }

    public function inventoryDetailLaboratory(): HasOneThrough
    {
        return $this->hasOneThrough(Laboratory::class, Inventory::class, 'id', 'id', 'inventory_id', 'laboratory_id');
    }
}
