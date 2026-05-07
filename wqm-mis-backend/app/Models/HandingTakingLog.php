<?php

namespace App\Models;

use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HandingTakingLog extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'user_id',
        'handing_taking_id',
        'handingable_type',
        'handingable_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('handing_taking_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Handing Taking Log has been {$eventName}");
    }


    public function handingable()
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function handingTaking(): BelongsTo
    {
        return $this->belongsTo(HandingTaking::class);
    }
}
