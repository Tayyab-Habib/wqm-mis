<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Region extends Model
{
    use HasFactory, CreatedModifiedByTrait, TimeStampAccessorTrait, LogsActivity;

    protected $fillable = [
        'name',
        'is_active',
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
            ->useLogName('regions')
            ->setDescriptionForEvent(fn(string $eventName) => "Region has been {$eventName}");
    }

    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }
}
