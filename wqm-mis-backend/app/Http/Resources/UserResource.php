<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $this->loadMissing(['phedDivision', 'district', 'circle', 'region']);
        $primaryRoleName = $this->roles[0]->name ?? 'viewer';

        // Plain list of permission slugs the user can perform (via roles + direct grants).
        // The legacy 'permissions' field stays encrypted for backwards compatibility;
        // 'permission_names' is the new plain-text array for frontend RBAC gating.
        //
        // AuthController mutates $user->permissions in-place to a Collection of strings
        // BEFORE UserResource runs (legacy decryption flow). If we call getAllPermissions()
        // again here, Spatie's HasPermissions::merge() tries to call getKey() on those
        // strings and crashes with "Call to a member function getKey() on string".
        // So: if $this->permissions is already a flat string collection, just use it.
        // Otherwise pluck names off the Permission models normally.
        $permRaw = $this->permissions;
        $permissionNames = collect($permRaw)
            ->map(fn ($p) => is_string($p) ? $p : ($p->name ?? null))
            ->filter()
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->image,
            'phone' => $this->phone,
            'token' => $this->token,
            'role' => ucwords(str_replace('-', ' ', $primaryRoleName)),
            'role_slug' => $primaryRoleName,
            'roles' => $this->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name])->all(),
            'district_id' => $this->district_id,
            'division_id' => $this->district->division_id ?? null,
            'phed_division_id' => $this->phed_division_id,
            'circle_id' => $this->circle_id,
            'region_id' => $this->region_id,
            'phed_division' => $this->phedDivision ? ['id' => $this->phedDivision->id, 'name' => $this->phedDivision->name] : null,
            'district' => $this->district ? ['id' => $this->district->id, 'name' => $this->district->name] : null,
            'circle' => $this->circle ? ['id' => $this->circle->id, 'name' => $this->circle->name] : null,
            'region' => $this->region ? ['id' => $this->region->id, 'name' => $this->region->name] : null,
            'laboratory' => $this->laboratories->first() ? ['id' => $this->laboratories->first()->id, 'name' => $this->laboratories->first()->name] : null,
            'permissions' => Crypt::encryptString(json_encode($this->permissions)),
            // ── RBAC plain-text plumbing ──
            'permission_names' => $permissionNames,
            'is_view_only'     => (bool) $this->is_view_only,
            'is_dummy'         => (bool) $this->is_dummy,
            'allowed_modules'  => $this->allowed_modules,
        ];
    }
}
