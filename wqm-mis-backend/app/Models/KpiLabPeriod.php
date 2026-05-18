<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiLabPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_id',
        'kpi_code',
        'period',
        'numerator',
        'denominator',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'numerator'   => 'integer',
        'denominator' => 'integer',
    ];

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Percentage rounded to 1 decimal — null when denominator is 0. */
    public function getValueAttribute(): ?float
    {
        if ($this->denominator <= 0) return null;
        return round(($this->numerator / $this->denominator) * 100, 1);
    }
}
