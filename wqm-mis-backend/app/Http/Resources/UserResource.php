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
        ];
    }
}
