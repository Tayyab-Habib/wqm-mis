<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Models\WaterSamples\WaterSample;
use App\Models\WaterSamples\WaterSampleInvoice;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'type',
        'organization_name',
        'password',
        'portal_token',
    ];
    protected $hidden = [
        'deleted_at',
        'password',
        'portal_token',
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
            ->useLogName('clients')
            ->setDescriptionForEvent(fn(string $eventName) => "Client has been {$eventName}");
    }

    public function waterSamples()
    {
        return $this->morphMany(WaterSample::class, 'collectable');
    }

    public function waterSampleInvoices(): HasMany
    {
        return $this->HasMany(WaterSampleInvoice::class);
    }
}
