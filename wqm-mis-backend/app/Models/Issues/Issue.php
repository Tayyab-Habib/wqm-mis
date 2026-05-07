<?php

namespace App\Models\Issues;

use App\Enums\IssueStatusEnum;
use App\Enums\ResponsibleTypeEnum;
use App\Models\Scopes\LatestScope;
use App\Models\User;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Issue extends Model
{
    use HasFactory, SoftDeletes,LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'user_id',
        'issuable_type',
        'issuable_id',
        'title',
        'file',
        'description',
        'status',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'responsible_type' => ResponsibleTypeEnum::class,
        'status' => IssueStatusEnum::class,
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
            ->useLogName('issues')
            ->setDescriptionForEvent(fn(string $eventName) => "Issue has been {$eventName}");
    }

    public function file(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value ? url(Storage::url($value)) : null,
        );
    }

    public function issuable(): MorphTo
    {
        return $this->morphTo();
    }

    public function issueLogs(): HasMany
    {
        return $this->hasMany(IssueLog::class);
    }

    public function issueResponsibles(): HasMany
    {
        return $this->hasMany(IssueResponsible::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['responsible_type', 'updated_at'])->withTimestamps();
    }
}
