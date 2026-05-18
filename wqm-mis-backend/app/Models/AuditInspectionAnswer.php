<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditInspectionAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_inspection_id',
        'audit_checklist_item_id',
        'answer',
        'notes',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(AuditInspection::class, 'audit_inspection_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AuditChecklistItem::class, 'audit_checklist_item_id');
    }
}
