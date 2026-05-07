<?php

namespace App\Models;

use App\Enums\PowerInputEnum;
use App\Models\PhedDivision;
use App\Models\Scopes\LatestScope;
use App\Models\WaterSamples\WaterSample;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\IsActiveScopeTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterScheme extends Model
{
    use HasFactory, SoftDeletes, CreatedModifiedByTrait, LogsActivity, TimeStampAccessorTrait, IsActiveScopeTrait;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'slug',
        'address',
        'created_by',
        'modified_by',
        'is_active',
        'source_type',
        'years_of_installation',
        'mode',
        'operation',
        'type_of_machine',
        'horse_power_motor',
        'storage',
        'power_input',
        'capacity',
        'depth',
        'population',
        'chamber',
        'pipe_type',
        'remarks',
        'union_council_id',
        'tehsil_id',
        'district_id',
        'division_id',
        'province_id',
        'phed_division_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'created_at' => 'datetime:M d, Y H:i:s',
        'updated_at' => 'datetime:M d, Y H:i:s',
        'power_input' => PowerInputEnum::class,
    ];

    protected static function booted()
    {
        static::created(function (WaterScheme $waterScheme) {
            $division = Division::query()
                ->select('abbreviation')
                ->find($waterScheme->division_id);

            $waterScheme->slug = 'WSS'
                . '-'
                . $division->abbreviation
                . '-'
                . str_pad($waterScheme->id, 4, '0', STR_PAD_LEFT);

            $waterScheme->save();
        });

        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water_scheme')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Scheme has been {$eventName}");
    }


    public function unionCouncil(): BelongsTo
    {
        return $this->belongsTo(UnionCouncil::class);
    }

    public function tehsil(): BelongsTo
    {
        return $this->belongsTo(Tehsil::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function waterSamples(): HasMany
    {
        return $this->hasMany(WaterSample::class);
    }

    public function phedDivision(): BelongsTo
    {
        return $this->belongsTo(PhedDivision::class);
    }

    public function waterSample(): HasOne
    {
        return $this->hasOne(WaterSample::class)->withoutGlobalScopes()->latestOfMany();
    }

    public function waterSchemeSchedules(): HasMany
    {
        return $this->hasMany(WaterSchemeSchedule::class);
    }

    public function lastWaterSchemeSchedules(): HasOne
    {
        return $this->hasOne(WaterSchemeScheduleLog::class)->latestOfMany();
    }



}
