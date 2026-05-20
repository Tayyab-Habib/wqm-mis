<?php

namespace App\Models\WaterSamples;

use App\Enums\CollectableTypeEnum;
use App\Enums\CollectedByEnum;
use App\Enums\CollectedInEnum;
use App\Enums\ReasonForTestingEnum;
use App\Enums\SamplingPointEnum;
use App\Enums\SourceTypeEnum;
use App\Casts\TolerantEnumCast;
use App\Enums\TestFrequencyEnum;
use App\Enums\WaterSampleCurrentStatusEnum;
use App\Enums\WaterSampleStatusEnum;
use App\Models\Circle;
use App\Models\District;
use App\Models\Division;
use App\Models\Laboratories\Laboratory;
use App\Models\Payment;
use App\Models\PhedDivision;
use App\Models\Province;
use App\Models\Region;
use App\Models\Scopes\LatestScope;
use App\Models\SubDivision;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use App\Models\User;
use App\Models\WaterScheme;
use App\Models\HubLab;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\DashboardFilterTrait;
use App\Traits\TimeStampAccessorTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterSample extends Model
{
    use HasFactory, SoftDeletes, CreatedModifiedByTrait, LogsActivity, TimeStampAccessorTrait, DashboardFilterTrait;

    protected $appends = ['created_at_formatted'];

    protected $fillable = [
        'test_type',
        'slug',
        'qr_code',
        'water_scheme_id',
        'sample_name',
        'source_type',
        'source_sub_type',
        'water_sample_address',
        'sampling_point',
        'collected_by',
        'collected_by_other',
        'latitude',
        'longitude',
        'temperature_in_celsius',
        'sampled_at',
        'analyzed_at',
        'reported_at',
        'collected_in',
        'collected_in_other',
        'complaint',
        'complaint_by_other',
        'desired_test',
        'created_by',
        'modified_by',
        'laboratory_id',
        'union_council_id',
        'tehsil_id',
        'district_id',
        'division_id',
        'province_id',
        'region_id',
        'circle_id',
        'phed_division_id',
        'hub_lab_id',
        'sub_division_id',
        'remarks',
        'result',
        'is_draft',
        'collectable_id',
        'collectable_type',
        // Logical sample kind: PHE / Private / PT. Distinguishes PT from PHE
        // since both store User::class in the polymorphic collectable_type.
        'sample_kind',
        'lab_incharge_id',
        'research_officer_id',
        'current_status',
        'current_round',
        'is_closed'
    ];

    protected $hidden = [
        'updated_at',
        'deleted_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        // D-02 fix — every legacy enum cast on this table is being made
        // tolerant of empty strings + case variations. The existing data
        // contains values like 'fresh' (lowercase) and '' (empty), and a
        // strict ::from() call from JSON serialisation crashes the entire
        // Finance Module surface area (clubbed-invoice billing-summary
        // accessor, SBP-pending list, etc.). No data migration required.
        'test_type'      => TolerantEnumCast::class . ':' . TestFrequencyEnum::class,
        'collected_by'   => TolerantEnumCast::class . ':' . CollectedByEnum::class,
        'source_type'    => TolerantEnumCast::class . ':' . SourceTypeEnum::class,
        'sampling_point' => TolerantEnumCast::class . ':' . SamplingPointEnum::class,
        'collected_in'   => TolerantEnumCast::class . ':' . CollectedInEnum::class,
        'complaint'      => TolerantEnumCast::class . ':' . ReasonForTestingEnum::class,
//        'desired_test' => DesiredTestEnum::class,
//        'sampled_at' => 'datetime:Y-m-d H:i:s',
        'is_draft' => 'boolean',
        'is_closed' => 'boolean',
        'current_status' => WaterSampleCurrentStatusEnum::class
    ];

    protected function sampledAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->setTimezone(config('app.timezone'))->format('d M, Y H:i') : null,
        );
    }

    protected function reportedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->setTimezone(config('app.timezone'))->format('d M, Y H:i') : null,
        );
    }

    protected function analyzedAt(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value)->setTimezone(config('app.timezone'))->format('d M, Y H:i') : null,
        );
    }

    protected function createdAtFormatted(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => isset($attributes['created_at']) ? Carbon::parse($attributes['created_at'])->setTimezone(config('app.timezone'))->format('d M, Y H:i') : null,
        );
    }

    public function desiredTest(): Attribute
    {
        return Attribute::make(get: fn ($value) => explode(', ', $value));
    }

    protected static function booted()
    {
        static::created(function (WaterSample $waterSample) {
            $division = Division::query()
                ->select('abbreviation')
                ->find($waterSample->division_id);

            // Slug kind segment: prefer the explicit sample_kind (so PT samples
            // get a 'PT' prefix even though their polymorphic collectable_type
            // is User::class, same as PHE). Fall back to the polymorphic-class
            // inference for legacy rows that haven't been backfilled.
            $kindSegment = $waterSample->sample_kind
                ?? ($waterSample->collectable_type === User::class
                    ? CollectableTypeEnum::PHE->value
                    : CollectableTypeEnum::PRIVATE->value);

            $waterSample->slug = now()->format('y')
                . '/'
                . $division->abbreviation
                . '/'
                . $kindSegment
                . '/'
                . str_pad($waterSample->id, 4, '0', STR_PAD_LEFT);

            $waterSample->qr_code = QrCode::size(100)->generate(url('water-samples/' . $waterSample->slug));

            $waterSample->save();
        });

        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water_samples')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Sample has been {$eventName}");
    }

    public function collectable(): MorphTo
    {
        return $this->morphTo();
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

    public function waterScheme(): BelongsTo
    {
        return $this->belongsTo(WaterScheme::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function phedDivision(): BelongsTo
    {
        return $this->belongsTo(PhedDivision::class);
    }

    public function hubLab(): BelongsTo
    {
        return $this->belongsTo(HubLab::class);
    }

    public function subDivision(): BelongsTo
    {
        return $this->belongsTo(SubDivision::class);
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function labIncharge(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function researchOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function waterSampleDetails(): HasMany
    {
        return $this->hasMany(WaterSampleDetail::class);
    }

    public function tests(): HasMany
    {
        return $this->hasMany(WaterSampleTest::class);
    }

    public function waterSampleInvoice(): HasOne
    {
        return $this->hasOne(WaterSampleInvoice::class);
    }

    public function payment()
    {
        return $this->morphMany(Payment::class, 'paymentable');
    }
}
