<?php

namespace App\Models;

use App\Models\Laboratories\Laboratory;
use App\Models\Scopes\LatestScope;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use HasFactory, SoftDeletes, CreatedModifiedByTrait, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'slug',
        'total',
        'laboratory_id',
        'description',
        'created_by',
        'modified_by',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected $casts = [
        'created_at' => 'datetime:M d, Y H:i:s',
        'updated_at' => 'datetime:M d, Y H:i:s',
    ];

    protected static function booted()
    {
        static::created(function (Payment $payment) {
            $payment->slug = 'PMT'
                . '-'
                . str_pad($payment->id, 4, '0', STR_PAD_LEFT);

            $payment->save();
        });

        static::addGlobalScope(new LatestScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('payments')
            ->setDescriptionForEvent(fn(string $eventName) => "Payment has been {$eventName}");
    }


    /**
     * Interact with the user's first name.
     *
     * @return Attribute
     */
    protected function total(): Attribute
    {

        return Attribute::make(
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100,
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function paymentDetails(): HasMany
    {
        return $this->HasMany(PaymentDetail::class);
    }
}
