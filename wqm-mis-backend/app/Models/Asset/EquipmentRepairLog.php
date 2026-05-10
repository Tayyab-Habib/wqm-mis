<?php

namespace App\Models\Asset;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentRepairLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'laboratory_asset_id',
        'fault_date',
        'fault_description',
        'repair_status',
        'technician',
        'resolved_date',
        'repair_cost',
        'remarks',
    ];

    protected $casts = [
        'fault_date'    => 'date:Y-m-d',
        'resolved_date' => 'date:Y-m-d',
        'repair_cost'   => 'decimal:2',
    ];

    public function laboratoryAsset(): BelongsTo
    {
        return $this->belongsTo(LaboratoryAsset::class);
    }
}
