<?php

namespace App\Models;

use App\Enums\SopWaterSampleEnum;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SopWaterSample extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'user_id',
        'type',
        'description',
    ];

    protected $casts = [
        'type' => SopWaterSampleEnum::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('sop_water_samples')
            ->setDescriptionForEvent(fn(string $eventName) => "SOP Water Sample has been {$eventName}");
    }

}
