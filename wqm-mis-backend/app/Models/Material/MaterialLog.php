<?php

namespace App\Models\Material;

use App\Enums\MaterialLogStatusEnum;
use App\Models\Scopes\LatestScope;
use App\Models\User;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaterialLog extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'material_id',
        'user_id',
        'date_of_expiry',
        'quantity',
        'unit',
        'date_of_entry',
        'status',
        'type',
        'recipient_name',
        'recipient_role',
        'sample_ref',
        'remarks',
        'recipient_lab_id',
        'demand_id',
        'dispatch_reference',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'date_of_expiry' => 'date',
        'date_of_entry' => 'date',
        'status' => MaterialLogStatusEnum::class,
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
            ->useLogName('stock_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Stock Log has been {$eventName}");
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
