<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PtRoundResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'pt_round_participant_id',
        'pt_round_item_id',
        'submitted_value',
        'deviation_pct',
        'passed',
        'notes',
    ];

    protected $casts = [
        'submitted_value' => 'decimal:4',
        'deviation_pct'   => 'decimal:4',
        'passed'          => 'boolean',
    ];

    public function participant(): BelongsTo
    {
        return $this->belongsTo(PtRoundParticipant::class, 'pt_round_participant_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PtRoundItem::class, 'pt_round_item_id');
    }
}
