<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtRoundItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pt_round_id',
        'test_id',
        'reference_value',
        'tolerance_pct',
        'unit',
        'notes',
    ];

    protected $casts = [
        'reference_value' => 'decimal:4',
        'tolerance_pct'   => 'decimal:2',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(PtRound::class, 'pt_round_id');
    }

    public function test(): BelongsTo
    {
        // Tests model lives under App\Models\Test if present; fall back to a
        // simple reference by id only (resolver-less) for portability.
        return $this->belongsTo(\App\Models\Test::class, 'test_id');
    }
}
