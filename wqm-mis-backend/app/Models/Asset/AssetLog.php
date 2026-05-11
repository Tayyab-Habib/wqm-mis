<?php

namespace App\Models\Asset;

use App\Enums\AssetLogStatusEnum;
use App\Models\Scopes\LatestScope;
use App\Models\User;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetLog extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'asset_id',
        'user_id',
        'quantity',
        'unit',
        'date_of_entry',
        'status',
        'type',
        'recipient_name',
        'recipient_role',
        'asset_ref',
        'remarks',
        'recipient_lab_id',
        'dispatch_reference',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $casts = [
        'status' => AssetLogStatusEnum::class,
        'created_at' => 'datetime:M d, Y H:i:s',
        'updated_at' => 'datetime:M d, Y H:i:s',
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
            ->useLogName('inventory_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Inventory Log has been {$eventName}");
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
