<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Permission;

class Module extends Model
{
    use HasFactory;

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function defaultPermissions(): HasMany
    {
        return $this->hasMany(Permission::class)->where('is_custom', '=', false);
    }

    public function customPermissions(): HasMany
    {
        return $this->hasMany(Permission::class)->where('is_custom', '=', true);
    }

    public function scopeRolesPermissions(Builder $query, string $relation, int $roleId)
    {
        return $query->with([
            $relation => fn($q1) => $q1->select(['id', 'name', 'module_id'])->withExists([
                'roles as has_permission' => fn($q2) => $q2->where('id', '=', $roleId),
            ]),
        ]);
    }
}
