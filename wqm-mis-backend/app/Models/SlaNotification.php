<?php

namespace App\Models;

use App\Enums\NotificationSlaStatusEnum;
use App\Models\WaterSamples\WaterSample;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaNotification extends DatabaseNotification
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id', 'type', 'notifiable_id', 'notifiable_type', 'data', 'read_at',
        'water_sample_id', 'round', 'role', 'status', 'notified_at', 'due_at', 'action_taken_at', 'type_key'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'array',
        'id' => 'string',
        'read_at' => 'datetime',
        'notified_at' => 'datetime',
        'due_at' => 'datetime',
        'action_taken_at' => 'datetime',
        'status' => NotificationSlaStatusEnum::class,
    ];

    /**
     * Get the water sample that this notification belongs to.
     */
    public function waterSample(): BelongsTo
    {
        return $this->belongsTo(WaterSample::class, 'water_sample_id');
    }
}
