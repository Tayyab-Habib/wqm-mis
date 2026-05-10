<?php

namespace App\Models\Asset;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentCalibrationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_asset_id',
        'calibration_date',
        'calibrated_by',
        'result',
        'certificate_ref',
        'standard_used',
        'next_due_date',
        'remarks',
    ];

    protected $casts = [
        'calibration_date' => 'date:Y-m-d',
        'next_due_date'    => 'date:Y-m-d',
    ];

    public function laboratoryAsset(): BelongsTo
    {
        return $this->belongsTo(LaboratoryAsset::class);
    }
}
