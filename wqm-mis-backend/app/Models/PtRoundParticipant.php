<?php

namespace App\Models;

use App\Models\Laboratories\Laboratory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PtRoundParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'pt_round_id',
        'laboratory_id',
        'status',
        'submitted_at',
        'submitted_by',
        'notes',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function round(): BelongsTo
    {
        return $this->belongsTo(PtRound::class, 'pt_round_id');
    }

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(PtRoundResult::class);
    }
}
