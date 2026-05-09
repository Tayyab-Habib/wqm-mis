<?php

namespace App\Models;

use App\Enums\DiaryDispatchEnum;
use App\Models\Scopes\LatestScope;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DiaryDispatch extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait, CreatedModifiedByTrait;

    protected $fillable = [
        'subject',
        'person_name',
        'date_on_letter',
        'receival_date',
        'attachment_name',
        'attachment',
        'type',
        'designation_id',
        'folder_id',
        'laboratory_id',
        'created_by',
        'modified_by',
        // SRS fields — shared
        'reference_no',
        'category',
        'priority',
        'remarks',
        // SRS fields — Diary (Inward)
        'from_sender',
        'addressed_to',
        'action_required',
        'action_due_date',
        'action_taken',
        'action_status',
        // SRS fields — Dispatch (Outward)
        'to_recipient',
        'reference_diary_no',
        'mode_of_dispatch',
        'dispatch_reference_no',
        'prepared_by',
        'dispatched_by',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $casts = [
        'type' => DiaryDispatchEnum::class,
    ];

    public function attachment(): Attribute
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
            ->useLogName('diary_dispatches')
            ->setDescriptionForEvent(fn(string $eventName) => "Diary/Dispatch has been {$eventName}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function laboratory(): BelongsTo
    {
    return $this->belongsTo(\App\Models\Laboratories\Laboratory::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
