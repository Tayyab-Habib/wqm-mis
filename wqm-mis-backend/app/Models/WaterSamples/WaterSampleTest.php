<?php

namespace App\Models\WaterSamples;

use App\Enums\ReasonForTestingEnum;
use App\Enums\SourceTypeEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Enums\WaterSampleTestStatusEnum;
use App\Enums\WaterSampleTestResultEnum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaterSampleTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'water_sample_id',
        'round',
        'sampling_point',
        'source_type',
        'source_sub_type',
        'complaint',
        'complaint_by_other',
        'desired_test',
        'on_demand_tests',
        'on_demand_test',
        'collected_by',
        'collected_in',
        'collected_in_other',
        'temperature_in_celsius',
        'sampled_at',
        'reported_at',
        'analyzed_at',
        'status',
        'result',
        'remarks',
        'lab_incharge_id',
        'research_officer_id',
        'is_final',
    ];

    protected $casts = [
        'sampled_at' => 'datetime',
        'reported_at' => 'datetime',
        'analyzed_at' => 'datetime',
        'is_final' => 'boolean',
        'status' => WaterSampleTestStatusEnum::class,
        'result' => WaterSampleTestResultEnum::class,
        'temperature_in_celsius' => 'decimal:2',
        'on_demand_test' => 'boolean',
        'source_type' => SourceTypeEnum::class,
        'complaint' => ReasonForTestingEnum::class,
        'on_demand_tests' => 'array',
    ];

    public function getDesiredTestAttribute($value)
    {
        return $value ? explode(', ', $value) : [];
    }

    public function setDesiredTestAttribute($value)
    {
        $this->attributes['desired_test'] = is_array($value) ? implode(', ', $value) : $value;
    }

    public function waterSample(): BelongsTo
    {
        return $this->belongsTo(WaterSample::class);
    }

    public function labIncharge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lab_incharge_id');
    }

    public function researchOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'research_officer_id');
    }

    public function waterSampleDetails()
    {
        return $this->hasMany(WaterSampleDetail::class, 'water_sample_test_id');
    }
}
