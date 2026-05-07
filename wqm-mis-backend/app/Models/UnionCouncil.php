<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class UnionCouncil extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'tehsil_id',
        'name'
    ];
    protected $hidden = [
        'deleted_at'
    ];

    public function tehsil(): BelongsTo
    {
        return $this->belongsTo(Tehsil::class);
    }

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('union_councils')
            ->setDescriptionForEvent(fn(string $eventName) => "Union Council has been {$eventName}");
    }


}
