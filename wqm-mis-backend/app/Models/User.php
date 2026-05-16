<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Asset\AssetMaintenanceLog;
use App\Models\Asset\AssetMaintenanceSchedule;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\InventoryLog;
use App\Models\Issues\Issue;
use App\Models\Issues\IssueLog;
use App\Models\Laboratories\Laboratory;
use App\Models\Laboratories\LaboratoryUser;
use App\Models\Scopes\LatestScope;
use App\Models\WaterSamples\WaterSampleDetail;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles, LogsActivity,CreatedModifiedByTrait, TimeStampAccessorTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'image',
        'gender',
        'date_of_birth',
        'date_of_joining',
        'is_active',
        'employee_status',
        'created_by',
        'modified_by',
        'career_background',
        'educational_background',
        'basic_pay_scale',
        'designation_id',
        'district_id',
        'region_id',
        'circle_id',
        'phed_division_id',
        // RBAC scaffolding
        'is_view_only',
        'is_dummy',
        'allowed_modules',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'deleted_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_view_only'      => 'boolean',
        'is_dummy'          => 'boolean',
        'allowed_modules'   => 'array',
    ];

    public function image(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url(Storage::url($value)) : null,
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
            ->useLogName('users')
            ->setDescriptionForEvent(fn(string $eventName) => "User has been {$eventName}");
    }


    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
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
    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function complaintLogs(): HasMany
    {
        return $this->hasMany(ComplaintLog::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function issueLogs(): HasMany
    {
        return $this->hasMany(IssueLog::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function assetMaintenanceLogs(): HasMany
    {
        return $this->hasMany(AssetMaintenanceLog::class);
    }

    public function diaryDispatches(): HasMany
    {
        return $this->hasMany(DiaryDispatch::class);
    }

    public function assetMaintenanceSchedules(): HasMany
    {
        return $this->hasMany(AssetMaintenanceSchedule::class);
    }

    public function laboratories(): BelongsToMany
    {
        return $this->belongsToMany(Laboratory::class)->withPivot('present_duty', 'assigned_parameters')->withTimestamps();
    }

    public function laboratoryDetails(): HasOne
    {
        return $this->hasOne(LaboratoryUser::class)->latest();
    }

    public function laboratoryUser(): HasOneThrough
    {
        return $this->hasOneThrough(Laboratory::class, LaboratoryUser::class, 'user_id', 'id', 'id', 'laboratory_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function responsibleIssues(): BelongsToMany
    {
        return $this->belongsToMany(Issue::class, 'issue_responsible', 'responsible_id')->withPivot(['responsible_type', 'updated_at'])->withTimestamps();
    }

    public function waterSampleDetails(): HasMany
    {
        return $this->hasMany(WaterSampleDetail::class);
    }

    /**
     * True for roles that bypass all data scoping (see global, no lab filter,
     * no hierarchy filter). Mirrors AuthScope::UNSCOPED_ROLES — keep them in
     * sync. Used by legacy controllers that still gate on a single role check.
     */
    public function isUnscoped(): bool
    {
        return $this->hasAnyRole([
            'system-administrator',
            'system-manager',
            'view-only-admin',
            'general-view-account',
        ]);
    }
}
