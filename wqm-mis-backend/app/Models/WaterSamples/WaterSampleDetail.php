<?php

namespace App\Models\WaterSamples;

use App\Models\Scopes\LatestScope;
use App\Models\Test;
use App\Models\User;
use App\Traits\DashboardFilterTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterSampleDetail extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait, DashboardFilterTrait;

    protected $fillable = [
        'water_sample_id',
        'water_sample_test_id',
        'test_id',
        'input_result',
        'analysis_result',
        'analyst_id'
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
            ->useLogName('water_sample_details')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Sample Detail has been {$eventName}");
    }

    public function waterSample(): BelongsTo
    {
        return $this->belongsTo(WaterSample::class);
    }

    public function waterSampleTest(): BelongsTo
    {
        return $this->belongsTo(WaterSampleTest::class);
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
