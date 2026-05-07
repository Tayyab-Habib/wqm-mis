<?php

namespace App\Models;

use App\Enums\FrequencyEnum;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterSchemeSchedule extends Model
{
    use HasFactory, CreatedModifiedByTrait, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'water_scheme_id',
        'day_of_month',
        'frequency',
        'is_recurring',
        'status',
        'laboratory_id',
        'created_by',
        'modified_by',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $casts = [
        'created_at' => 'datetime:M d, Y H:i:s',
        'updated_at' => 'datetime:M d, Y H:i:s',
    ];

    public function dayOfMonth(): Attribute
    {
        return Attribute::make(
            get: fn(string $value, array $attributes) => $attributes['frequency'] === FrequencyEnum::YEARLY->value
                ? Carbon::createFromFormat('m-d', $value)->format('M d')
                : $value,
        );
    }

    public function waterScheme(): BelongsTo
    {
        return $this->belongsTo(WaterScheme::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water_scheme_schedules')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Scheme Schedule has been {$eventName}");
    }

    public function waterSchemeScheduleLogs(): HasMany
    {
        return $this->hasMany(WaterSchemeScheduleLog::class, 'wss_schedule_id');
    }

}


