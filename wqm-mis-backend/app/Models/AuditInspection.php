<?php

namespace App\Models;

use App\Models\Laboratories\Laboratory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditInspection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'laboratory_id',
        'inspector_id',
        'inspection_date',
        'status',
        'notes',
        'evidence_file',
        'created_by',
    ];

    protected $casts = [
        'inspection_date' => 'date',
    ];

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AuditInspectionAnswer::class);
    }

    /**
     * SOP compliance score for this inspection.
     *   pass / (pass + fail) × 100
     * Excludes N/A answers from both numerator and denominator.
     * Returns null when there are no in-scope (non-NA) answers.
     */
    public function getScorePctAttribute(): ?float
    {
        $pass = $this->answers->where('answer', 'pass')->count();
        $fail = $this->answers->where('answer', 'fail')->count();
        $total = $pass + $fail;
        if ($total === 0) return null;
        return round(($pass / $total) * 100, 1);
    }
}
