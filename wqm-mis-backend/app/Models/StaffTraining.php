<?php

namespace App\Models;

use App\Models\Laboratories\Laboratory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StaffTraining extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'laboratory_id',
        'user_id',
        'staff_name',
        'training_topic',
        'training_date',
        'valid_until',
        'evidence_file',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'training_date' => 'date',
        'valid_until'   => 'date',
    ];

    public function laboratory(): BelongsTo
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
