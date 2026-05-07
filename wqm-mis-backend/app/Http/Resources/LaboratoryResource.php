<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'email' => $this->email,
            'address' => $this->address,
            'created_by' => $this->address,
            'modified_by' => $this->modified_by,
            'focal_person_id' => $this->focal_person_id,
            'logo' => $this->logo,
            'is_active' => $this->is_active,
            'union_council_id' => $this->union_council_id,
            'tehsil_id' => $this->tehsil_id,
            'district_id' => $this->district_id,
            'division_id' => $this->division_id,
            'province_id' => $this->province_id,
            'focal_person' => [
                'id' => $this->focalPerson?->id,
                'name' => $this->focalPerson?->name,
            ],
            'designation' => [
                'id' => $this->focalPerson?->designation?->id,
                'name' => $this->focalPerson?->designation?->name,
            ],
            'province' => $this->province,
            'division' => $this->division,
            'district' => $this->district,
            'tehsil' => $this->tehsil,
            'unionCouncil' => $this->unionCouncil,
        ];
    }
}
