<?php

namespace App\Models\Laboratories;

use App\Models\Asset\LaboratoryAsset;
use App\Models\District;
use App\Models\Division;
use App\Models\Folder;
use App\Models\Inventory\Inventory;
use App\Models\Material\LaboratoryMaterial;
use App\Models\Payment;
use App\Models\Province;
use App\Models\Scopes\LatestScope;
use App\Models\Tehsil;
use App\Models\UnionCouncil;
use App\Models\User;
use App\Models\WaterSamples\WaterSample;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\DashboardFilterTrait;
use App\Traits\IsActiveScopeTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @mixin Builder
 */
class Laboratory extends Model
{
    use HasFactory, SoftDeletes, CreatedModifiedByTrait, LogsActivity, TimeStampAccessorTrait, IsActiveScopeTrait, DashboardFilterTrait;

    protected $fillable = [
        'name',
        'code',
        'latitude',
        'longitude',
        'phone',
        'fax',
        'email',
        'address',
        'created_by',
        'modified_by',
        'focal_person_id',
        'logo',
        'is_active',
        'union_council_id',
        'tehsil_id',
        'district_id',
        'division_id',
        'province_id',
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
            ->useLogName('laboratories')
            ->setDescriptionForEvent(fn(string $eventName) => "Laboratory has been {$eventName}");
    }

    public function logo(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url(Storage::url($value)) : null,
        );
    }

    public function focalPerson(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('present_duty', 'assigned_parameters')->withTimestamps();
    }

    public function waterSamples(): HasMany
    {
        return $this->hasMany(WaterSample::class);
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function laboratoryAssets(): HasMany
    {
        return $this->hasMany(LaboratoryAsset::class);
    }

    public function laboratoryMaterials(): HasMany
    {
        return $this->hasMany(LaboratoryMaterial::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function coveredDistricts(): BelongsToMany
    {
        return $this->belongsToMany(District::class)->withTimestamps();
    }
}
