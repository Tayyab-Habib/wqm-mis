<?php

namespace App\Models;

use App\Enums\WaterSampleResultEnum;
use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Models\WaterSamples\WaterSample;
use App\Traits\TimeStampAccessorTrait;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class District extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'division_id',
        'circle_id',
        'name'
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
            ->useLogName('districts')
            ->setDescriptionForEvent(fn(string $eventName) => "District has been {$eventName}");
    }


    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function phedDivisions(): HasMany
    {
        return $this->hasMany(PhedDivision::class);
    }

    public function tehsils(): HasMany
    {
        return $this->hasMany(Tehsil::class);
    }

    public function laboratories(): HasMany
    {
        return $this->hasMany(Laboratory::class);
    }

    public function waterSamples(): HasMany
    {
        return $this->hasMany(WaterSample::class);
    }

    /**
     * Scope a query to only include users of a given type.
     *
     * @param Builder $query
     * @param string $key
     * @param CarbonImmutable $startDate
     * @param CarbonImmutable $endDate
     * @return Builder
     */
    public function scopeFilterByDateRange(Builder $query, string $key, CarbonImmutable $startDate, CarbonImmutable $endDate): Builder
    {
        return $query->withCount([
            "waterSamples as {$key}_fit_water_samples" => function ($query) use ($startDate, $endDate) {
                $query->where('result', '=', WaterSampleResultEnum::FIT->value)
                    ->whereBetween('water_samples.sampled_at', [$startDate, $endDate]);
            },
            "waterSamples as {$key}_unfit_water_samples" => function ($query) use ($startDate, $endDate) {
                $query->where('result', '=', WaterSampleResultEnum::UNFIT->value)
                    ->whereBetween('water_samples.sampled_at', [$startDate, $endDate]);
            },
            "waterSamples as {$key}_total_water_samples" => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('water_samples.sampled_at', [$startDate, $endDate]);
            },
        ]);
    }

    public function waterSchemes(): HasMany
    {
        return $this->hasMany(WaterScheme::class);
    }

    public function scopeApplyFilters(Builder $query, $data)
    {
        if (isset($data->water_scheme_id)) {
            $query->whereHas('waterSamples', function ($subQuery) use ($data) {
                $subQuery->where('water_scheme_id', '=', $data->water_scheme_id);
            });
        }

        if (isset($data->sampling_point)) {
            $query->whereHas('waterSamples', function ($subQuery) use ($data) {
                $subQuery->where('sampling_point', '=', $data->sampling_point);
            });
        }

        if (isset($data->collected_by)) {
            $query->whereHas('waterSamples', function ($subQuery) use ($data) {
                $subQuery->where('collected_by', '=', $data->collected_by);
            });
        }

        if (isset($data->source_type)) {
            $query->whereHas('waterSamples', function ($subQuery) use ($data) {
                $subQuery->where('source_type', '=', $data->source_type);
            });
        }

        if (isset($data->union_council_id)) {
            $query->where('union_council_id', '=', $data->union_council_id);
        }

        if (isset($data->tehsil_id)) {
            $query->where('tehsil_id', '=', $data->tehsil_id);
        }

        if (isset($data->district_id)) {
            $query->where('district_id', '=', $data->district_id);
        }

        if (isset($data->starting_date, $data->ending_date)) {
            $query->whereHas('waterSamples', function ($subQuery) use ($data) {
                $subQuery->whereBetween('sampled_at', [$data->starting_date, $data->ending_date]);
            });
        }
    }

}
