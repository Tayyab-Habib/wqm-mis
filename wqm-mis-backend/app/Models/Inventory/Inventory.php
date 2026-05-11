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
        'urgency',
        'justification',
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
            // SRS §2.7-4: Demand ID format DMD/YY/LAB-CODE/XXXX
            // e.g. DMD/26/KHT/0012
            $lab = $inventory->laboratory;
            $labCode = $lab?->code
                ?: ($lab ? strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $lab->name), 0, 3)) : 'GEN');
            $year = now()->format('y');
            $seq = str_pad($inventory->id, 4, '0', STR_PAD_LEFT);

            $inventory->slug = "DMD/{$year}/{$labCode}/{$seq}";
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
