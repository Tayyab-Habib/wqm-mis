<?php

namespace App\Models\Material;

use App\Models\Laboratories\Laboratory;
use App\Traits\TimeStampAccessorTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaboratoryMaterialLog extends Model
{
    use HasFactory, LogsActivity, TimeStampAccessorTrait;

    protected $fillable = [
        'laboratory_material_id',
        'material_log_id',
        'date_of_expiry',
        'quantity',
        'unit',
        'status',
        'type',
        'recipient_name',
        'recipient_role',
        'sample_ref',
        'remarks',
        'recipient_lab_id',
        'demand_id',
        'dispatch_reference',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable)
            ->logOnlyDirty()
            ->useLogName('laboratory_stock_logs')
            ->setDescriptionForEvent(fn(string $eventName) => "Laboratory Stock Log has been {$eventName}");
    }


    public function LaboratoryMaterial(): BelongsTo
    {
        return $this->belongsTo(LaboratoryMaterial::class);
    }

    public function materialLog(): BelongsTo
    {
        return $this->belongsTo(MaterialLog::class);
    }

    public function recipientLab(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class, 'recipient_lab_id');
    }
}
