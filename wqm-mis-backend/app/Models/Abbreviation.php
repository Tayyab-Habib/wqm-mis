<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Abbreviation extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'name',
        'detail',
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
            ->useLogName('abbreviations')
            ->setDescriptionForEvent(fn(string $eventName) => "Abbreviation has been {$eventName}");
    }

}
