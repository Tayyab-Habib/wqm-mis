<?php

namespace App\Models;

use App\Enums\InvoiceableTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use App\Models\Scopes\LatestScope;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Unit extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'name',
        'type',
    ];

    protected $hidden = [
        'deleted_at'
    ];

    protected static function booted()
    {
        static::addGlobalScope(new LatestScope());
    }

    public function type(): Attribute
    {
        return Attribute::get(function ($value){
            return ucfirst(mb_strtolower(($value === InvoiceableTypeEnum::STOCK->value
                ?  InvoiceableTypeEnum::STOCK->name
                :  InvoiceableTypeEnum::INVENTORY->name)));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('units')
            ->setDescriptionForEvent(fn(string $eventName) => "Unit has been {$eventName}");
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'unit', 'name');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'unit', 'name');
    }

}
