<?php

namespace App\Models;

use App\Models\Laboratories\Laboratory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class VerificationVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'laboratory_id',
        'technical_head_id',
        'visit_date',
        'samples_verified',
        'samples_matched',
        'observations',
        'evidence_file',
        'created_by',
    ];

    protected $casts = [
        'visit_date'       => 'date',
        'samples_verified' => 'integer',
        'samples_matched'  => 'integer',
    ];

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function technicalHead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technical_head_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function samples(): HasMany
    {
        return $this->hasMany(VerificationVisitSample::class);
    }

    /** Match rate for this single visit. Null when no samples were verified. */
    public function getMatchRateAttribute(): ?float
    {
        if ($this->samples_verified <= 0) return null;
        return round(($this->samples_matched / $this->samples_verified) * 100, 1);
    }
}
