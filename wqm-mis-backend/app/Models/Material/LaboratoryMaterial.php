<?php

namespace App\Models\Material;

use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaboratoryMaterial extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'laboratory_id',
        'material_id',
        'quantity',
        'available_quantity',
        'unit',
        'threshold',
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
            ->useLogName('laboratory_stocks')
            ->setDescriptionForEvent(fn(string $eventName) => "Laboratory Stock has been {$eventName}");
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function laboratoryMaterialLogs(): HasMany
    {
        return $this->hasMany(LaboratoryMaterialLog::class);
    }
}
