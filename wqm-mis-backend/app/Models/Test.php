<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Test extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'type',
        'water_quality_parameter',
        'unit',
        'criteria',
        'detectable_limit',
        'reference_method',
        'who_guideline_start',
        'who_guideline_end',
        'laboratory_guideline_start',
        'laboratory_guideline_end',
        'rate',
        'is_active',
        'display_order',
        'created_by',
        'modified_by',
    ];

    protected $appends = [
        'water_quality_parameter_unit'
    ];

    protected $casts = [
        'criteria' => 'bool',
    ];

    /**
     * concatenate water_quality_parameter and unit.
     *
     * @return Attribute
     */
    protected function WaterQualityParameterUnit(): Attribute
    {
        return new Attribute(
            get: fn($value, $attribute) => $attribute['unit']
                ? $attribute['water_quality_parameter'] . ' - ' . $attribute['unit']
                : $attribute['water_quality_parameter'],
        );
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
            ->useLogName('tests')
            ->setDescriptionForEvent(fn(string $eventName) => "Test has been {$eventName}");
    }

    public function waterSampleDetails(): HasMany
    {
        return $this->HasMany(WaterSampleDetail::class);
    }

    public function waterSample(): HasOneThrough
    {
        return $this->hasOneThrough(\App\Models\WaterSamples\WaterSample::class, WaterSampleDetail::class, 'water_sample_id', 'id', 'id', 'water_sample_id')
            ->latest('water_samples.sampled_at');
    }
}
