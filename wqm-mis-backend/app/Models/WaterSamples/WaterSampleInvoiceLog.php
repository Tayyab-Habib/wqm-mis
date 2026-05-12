<?php

namespace App\Models\WaterSamples;

use App\Models\PaymentDetail;
use App\Models\User;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WaterSampleInvoiceLog extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'water_sample_invoice_id',
        'user_id',
        'paid',
        'balance',
        'payment_mode',
        'note',
        'sbp_submission_id',
    ];

    public function sbpSubmission(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SbpSubmission::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water_sample_invoice_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Water Sample Invoice Log has been {$eventName}");
    }

    public function waterSampleInvoice(): BelongsTo
    {
        return $this->belongsTo(WaterSampleInvoice::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(PaymentDetail::class, 'paymentable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

