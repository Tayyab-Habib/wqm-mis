<?php

namespace App\Models;

use App\Models\Scopes\LatestScope;
use App\Traits\CreatedModifiedByTrait;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait, CreatedModifiedByTrait;

    protected $fillable = [
        'slug',
        'description',
        'amount',
        'file',
        'created_by',
        'modified_by',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
        static::created(function (Invoice $invoice) {

            $invoice->slug = 'INV-' . str_pad($invoice->id, 4, '0', STR_PAD_LEFT);

            $invoice->save();
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('invoices')
            ->setDescriptionForEvent(fn(string $eventName) => "Invoice has been {$eventName}");
    }


    public function file(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? url(Storage::url($value)) : null,
        );
    }

    /**
     * Interact with the user's first name.
     *
     * @return Attribute
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $value / 100,
            set: fn($value) => $value * 100,
        );
    }

    public function invoiceDetails(): HasMany
    {
        return $this->HasMany(InvoiceDetail::class);
    }
}
