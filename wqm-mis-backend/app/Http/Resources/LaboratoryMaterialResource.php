<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryMaterialResource extends JsonResource
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
            'name' => $this->material?->name,
            'quantity' => $this->quantity,
            'available_quantity' => $this->available_quantity,
            'unit' => $this->unit,
            'threshold' => $this->threshold,
            'status' => $this->status,
            'material_logs' => $this->when(($this->whenLoaded('laboratoryMaterialLogs')), LaboratoryMaterialLogResource::collection($this->laboratoryMaterialLogs))
        ];
    }
}
