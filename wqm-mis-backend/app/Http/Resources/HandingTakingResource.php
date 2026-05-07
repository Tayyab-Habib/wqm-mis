<?php

namespace App\Http\Resources;

use App\Models\Material\LaboratoryMaterial;
use Illuminate\Http\Resources\Json\JsonResource;

class HandingTakingResource extends JsonResource
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
            'created_by' => $this->createdByUser?->name,
            'modified_by' => $this->modifiedByUser?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'assigned_to' => $this->assignedTo?->name,
            'laboratory_name' => $this->laboratory?->name,
            'stockable' => [
                'id' => $this->when($this->stockable_type === LaboratoryMaterial::class, $this->stockable?->material?->id, $this->stockable?->asset?->id),
                'name' => $this->when($this->stockable_type === LaboratoryMaterial::class, $this->stockable?->material?->name, $this->stockable?->asset?->name),
            ]
        ];
    }
}
