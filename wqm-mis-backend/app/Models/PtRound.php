<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PtRound extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'round_date',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'round_date' => 'date',
        'due_date'   => 'date',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PtRoundItem::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(PtRoundParticipant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
