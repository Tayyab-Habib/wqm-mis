<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubDivision extends Model
{
    use HasFactory;

    protected $fillable = [
        'phed_division_id',
        'name',
    ];

    public function phedDivision(): BelongsTo
    {
        return $this->belongsTo(PhedDivision::class);
    }
}
