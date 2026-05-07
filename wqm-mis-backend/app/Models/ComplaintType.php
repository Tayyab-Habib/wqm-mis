<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ComplaintType extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'name',
    ];
    protected $hidden = [
        'deleted_at'
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
            ->useLogName('complaint_types')
            ->setDescriptionForEvent(fn(string $eventName) => "Complaint Type has been {$eventName}");
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }
}
