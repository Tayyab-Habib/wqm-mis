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
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar' => $this->image,
            'token' => $this->token,
            'role' => ucwords(str_replace('-', ' ', $this->roles[0]->name)),
            'district_id' => $this->district_id,
            'division_id' => $this->district->division_id,
            'permissions' => Crypt::encryptString(json_encode($this->permissions)),
        ];
    }
}
