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
        'is_clubbed',
        'clubbed_invoice_id',
        'period_from',
        'period_to',
        'clubbed_slug',
        'online_viewing_password',
    ];

    protected $appends = [
        'billing_summary',
        'category_name',
        'status_label',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected $casts = [
        'status'   => WaterSampleInvoiceStatusEnum::class,
        'is_clubbed' => 'boolean',
    ];

    /**
     * F-02 — SRS-compliant status label (Unpaid / Partially Paid / Paid).
     */
    public function getStatusLabelAttribute(): string
    {
        if ($this->status instanceof WaterSampleInvoiceStatusEnum) {
            return $this->status->label();
        }
        $enum = WaterSampleInvoiceStatusEnum::tryFrom((string) $this->status);
        return $enum ? $enum->label() : 'Unpaid';
    }

    public function getBillingSummaryAttribute()
    {
        $items = [];
        if ($this->is_clubbed) {
            $children = $this->relationLoaded('childInvoices')
                ? $this->childInvoices
                : $this->childInvoices()->with('waterSample.waterSampleDetails.test')->get();

            foreach ($children as $child) {
                $cat = $child->calculateCategory();
                $items[$cat]['count'] = ($items[$cat]['count'] ?? 0) + 1;
                $items[$cat]['rate']  = (float) $child->price;
                $items[$cat]['receipt_ids'] = array_merge(
                    $items[$cat]['receipt_ids'] ?? [],
                    [$child->waterSample?->slug ?? ('INV-' . $child->id)]
                );
            }
        } else {
            $cat = $this->calculateCategory();
            $items[$cat]['count'] = 1;
            $items[$cat]['rate']  = (float) $this->price;
            $items[$cat]['receipt_ids'] = [$this->waterSample?->slug ?? ('INV-' . $this->id)];
        }

        $formulaParts = [];
        $total        = 0;
        foreach ($items as $cat => $data) {
            $formulaParts[] = $data['count'] . ' × ' . number_format($data['rate']);
            $total         += $data['count'] * $data['rate'];
        }

        return [
            'items'   => $items,
            'formula' => implode(' + ', $formulaParts) . ' = PKR ' . number_format($total),
            'total'   => $total,
        ];
    }

    public function getCategoryNameAttribute()
    {
        return $this->calculateCategory();
    }

    public function calculateCategory()
    {
        if ($this->is_clubbed) {
            return 'Clubbed Invoice';
        }
        if (!$this->waterSample) {
            return 'N/A';
        }

        $types = $this->waterSample->waterSampleDetails()
            ->join('tests', 'water_sample_details.test_id', '=', 'tests.id')
            ->select('tests.type')
            ->distinct()
            ->pluck('type')
            ->toArray();

        $hasP = in_array('Physical', $types, true);
        $hasC = in_array('Chemical', $types, true);
        $hasM = false;
        foreach ($types as $t) {
            if (is_string($t) && str_contains($t, 'Microbiological')) {
                $hasM = true;
                break;
            }
        }

        if ($hasP && $hasC && $hasM) return 'PCM';
        if ($hasP && $hasC)          return 'PC';
        if ($hasM)                   return 'M';
        if ($hasP)                   return 'P';
        if ($hasC)                   return 'C';
        return 'General';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('water_sample_invoices')
            ->setDescriptionForEvent(fn (string $eventName) => "Water Sample Invoice has been {$eventName}");
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

    public function childInvoices(): HasMany
    {
        return $this->hasMany(WaterSampleInvoice::class, 'clubbed_invoice_id');
    }

    public function parentClubbedInvoice(): BelongsTo
    {
        return $this->belongsTo(WaterSampleInvoice::class, 'clubbed_invoice_id');
    }
}
