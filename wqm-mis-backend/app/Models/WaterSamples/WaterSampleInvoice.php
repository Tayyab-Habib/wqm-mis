<?php

namespace App\Models\WaterSamples;

use App\Enums\WaterSampleInvoiceStatusEnum;
use App\Models\Client;
use App\Models\PaymentDetail;
use App\Models\Scopes\LatestScope;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterSampleInvoice extends Model
{
    use HasFactory, SoftDeletes, CreatedModifiedByTrait, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'water_sample_id',
        'invoiceable_id',
        'invoiceable_type',
        'discount_percentage',
        'price',
        'paid',
        'balance',
        'status',
        'net_amount',
        'created_by',
        'modified_by',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'status' => WaterSampleInvoiceStatusEnum::class,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water_sample_invoices')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Sample Invoice has been {$eventName}");
    }

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
    }

    public function invoiceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function waterSample(): BelongsTo
    {
        return $this->belongsTo(WaterSample::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(PaymentDetail::class, 'paymentable');
    }

    public function waterSampleInvoiceLogs(): HasMany
    {
        return $this->hasMany(WaterSampleInvoiceLog::class);
    }
}
