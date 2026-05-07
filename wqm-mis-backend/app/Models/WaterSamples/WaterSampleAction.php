<?php

namespace App\Models\WaterSamples;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class WaterSampleAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'water_sample_id',
        'user_id',
        'round',
        'action_type',
        'details',
        'attachments',
        'action_date',
    ];

    protected $casts = [
        'action_date' => 'date',
        'attachments' => 'array',
    ];

    public function waterSample()
    {
        return $this->belongsTo(WaterSample::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
