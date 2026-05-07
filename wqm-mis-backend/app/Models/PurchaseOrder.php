<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'slug',
        'status',
        'date_of_order',
    ];

    protected $hidden = [
        'updated_at'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
        static::created(function (PurchaseOrder $purchaseOrder) {

            $purchaseOrder->slug = 'PO-' . str_pad($purchaseOrder->id, 4, '0', STR_PAD_LEFT);

            $purchaseOrder->save();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('purchase_orders')
            ->setDescriptionForEvent(fn(string $eventName) => "Purchase Order has been {$eventName}");
    }



    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'status' => PurchaseOrderStatus::class,
    ];


    public function purchaseOrderDetails(): HasMany
    {
        return $this->hasMany(PurchaseOrderDetail::class);
    }
}
