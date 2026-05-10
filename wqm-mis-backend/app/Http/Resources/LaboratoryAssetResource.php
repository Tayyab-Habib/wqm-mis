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
            'id'                     => $this->id,
            'asset_id'               => $this->asset_id,
            // equipment identity
            'name'                   => $this->asset?->name,
            'make_model'             => $this->make_model ?? $this->asset?->specification,
            // dates
            'purchased_at'           => $this->purchased_at,
            // calibration
            'calibration_cycle'      => $this->calibration_cycle,
            'next_calibration_date'  => $this->next_calibration_date,
            // operational
            'status'                 => $this->status,
            'quantity'               => $this->quantity,
            'unit'                   => $this->unit,
            'created_at'             => $this->created_at,
            // conditionally loaded relationships
            'asset_logs'                   => $this->when($this->whenLoaded('laboratoryAssetLogs'), LaboratoryAssetLogResource::collection($this->laboratoryAssetLogs)),
            'asset_maintenance_schedules'  => $this->when($this->whenLoaded('assetMaintenanceSchedules'), $this->assetMaintenanceSchedules),
        ];
    }
}
