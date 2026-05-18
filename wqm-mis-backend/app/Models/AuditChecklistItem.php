<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditChecklistItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'position',
        'question',
        'category',
        'is_active',
    ];

    protected $casts = [
        'position'  => 'integer',
        'is_active' => 'boolean',
    ];
}
