<?php

namespace App\Models\Inventory;

use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Models\User;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Inventory extends Model
{
    use HasFactory, SoftDeletes, CreatedModifiedByTrait, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'slug',
        'status',
        'created_by',
        'laboratory_id',
        'modified_by',
    ];

    protected $hidden = [
//        'created_at',
//        'updated_at',
        'deleted_at'
    ];

    protected $casts = [
        'created_at' => 'datetime:M d, Y H:i:s',
        'updated_at' => 'datetime:M d, Y H:i:s',
    ];

    protected static function booted()
    {
        static::created(function (Inventory $inventory) {

            $inventory->slug = 'INVR-' . str_pad($inventory->id, 4, '0', STR_PAD_LEFT);

            $inventory->save();
        });
        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('inventory_requests')
            ->setDescriptionForEvent(fn(string $eventName) => "Inventory Request has been {$eventName}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function inventoryDetails(): HasMany
    {
        return $this->hasMany(InventoryDetail::class);
    }
}
