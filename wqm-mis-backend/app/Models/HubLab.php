<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HubLab extends Model
{
    use HasFactory, CreatedModifiedByTrait, TimeStampAccessorTrait, LogsActivity;

    protected $fillable = [
        'name',
        'division_id',
        'created_by',
        'modified_by',
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
            ->useLogName('hub_labs')
            ->setDescriptionForEvent(fn(string $eventName) => "Hub Lab has been {$eventName}");
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class);
    }
}
