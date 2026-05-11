<?php

namespace App\Models\Material;

use App\Enums\MaterialStatusEnum;
use App\Models\Inventory\Inventory;
use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Traits\IsActiveScopeTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Material extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait, IsActiveScopeTrait;

    protected $fillable = [
        'name',
        'category',
        'quantity',
        'available_quantity',
        'unit',
        'threshold',
        'supplier',
        'status',
        'is_active',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'status' => MaterialStatusEnum::class,
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
            ->useLogName('stocks')
            ->setDescriptionForEvent(fn(string $eventName) => "Stock has been {$eventName}");
    }
    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function materialLogs(): HasMany
    {
        return $this->hasMany(MaterialLog::class);
    }

    public function inventory()
    {
        return $this->morphMany(Inventory::class, 'inventoryable');
    }

    public function laboratoryMaterials(): HasMany
    {
        return $this->hasMany(LaboratoryMaterial::class);
    }
}
