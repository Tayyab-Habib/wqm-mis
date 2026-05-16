<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Laboratories\Laboratory;
use App\Models\WaterSamples\WaterSampleInvoiceLog;

class SbpSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_slug',
        'laboratory_id',
        'period_from',
        'period_to',
        'amount',
        'challan_no',
        'deposit_date',
        'submitted_by_id',
        'submitted_by_name',
        'status',
        'remarks',
        'attachment_path',
        'verified_at',
        'verified_by_id'
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'deposit_date' => 'date',
        'verified_at' => 'datetime',
    ];

    public function laboratory()
    {
        return $this->belongsTo(Laboratory::class);
    }

    public function invoiceLogs()
    {
        return $this->hasMany(WaterSampleInvoiceLog::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_id');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }
}
