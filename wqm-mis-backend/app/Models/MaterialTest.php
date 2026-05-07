<?php

namespace App\Models;

use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaterialTest extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('stock_tests')
            ->setDescriptionForEvent(fn(string $eventName) => "Stock Test has been {$eventName}");
    }

}
