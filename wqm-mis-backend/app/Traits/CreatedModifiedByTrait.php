<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait CreatedModifiedByTrait
{
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifiedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modified_by');
    }
}
