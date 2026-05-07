<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryAssetResource extends JsonResource
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
            'name' => $this->asset?->name,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'threshold' => $this->threshold,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'asset_logs' => $this->when($this->whenLoaded('laboratoryAssetLogs'), LaboratoryAssetLogResource::collection($this->laboratoryAssetLogs)),
            'asset_maintenance_schedules' => $this->when($this->whenLoaded('assetMaintenanceSchedules'), $this->assetMaintenanceSchedules)
        ];
    }
}
