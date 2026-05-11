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
            // equipment identity (flattened for convenience)
            'name'                   => $this->asset?->name,
            'make_model'             => $this->make_model ?? $this->asset?->specification,
            'serial_number'          => $this->serial_number,
            // dates
            'purchased_at'           => $this->purchased_at,
            'warranty_expiry'        => $this->warranty_expiry,
            'purchase_value'         => $this->purchase_value,
            // calibration
            'calibration_cycle'      => $this->calibration_cycle,
            'next_calibration_date'  => $this->next_calibration_date,
            // operational
            'status'                 => $this->status,
            'quantity'               => $this->quantity,
            'unit'                   => $this->unit,
            'created_at'             => $this->created_at,
            // Nested asset so frontend filters on kind/item_code work.
            'asset' => $this->whenLoaded('asset', function () {
                return [
                    'id'                => $this->asset->id,
                    'name'              => $this->asset->name,
                    'kind'              => $this->asset->kind,
                    'category'          => $this->asset->category,
                    'item_code'         => $this->asset->item_code,
                    'condition'         => $this->asset->condition,
                    'date_of_purchase'  => $this->asset->date_of_purchase,
                    'purchase_value'    => $this->asset->purchase_value,
                    'location'          => $this->asset->location,
                    'last_verified'     => $this->asset->last_verified,
                    'remarks'           => $this->asset->remarks,
                    'specification'     => $this->asset->specification,
                    'country'           => $this->asset->country,
                    'agency'            => $this->asset->agency,
                ];
            }),
            // conditionally loaded relationships
            'asset_logs'                   => $this->when($this->whenLoaded('laboratoryAssetLogs'), LaboratoryAssetLogResource::collection($this->laboratoryAssetLogs)),
            'asset_maintenance_schedules'  => $this->when($this->whenLoaded('assetMaintenanceSchedules'), $this->assetMaintenanceSchedules),
        ];
    }
}
