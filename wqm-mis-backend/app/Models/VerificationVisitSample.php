<?php

namespace App\Models;

use App\Models\WaterSamples\WaterSample;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationVisitSample extends Model
{
    use HasFactory;

    protected $fillable = [
        'verification_visit_id',
        'water_sample_id',
        'sample_slug',
        'matched',
        'notes',
    ];

    protected $casts = [
        'matched' => 'boolean',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VerificationVisit::class, 'verification_visit_id');
    }

    public function waterSample(): BelongsTo
    {
        return $this->belongsTo(WaterSample::class);
    }
}
