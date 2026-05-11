<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class LaboratoryMaterialLogResource extends JsonResource
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
            'id'                 => $this->id,
            'quantity'           => $this->quantity,
            'unit'               => $this->unit,
            'status'             => $this->status,
            'type'               => $this->type,
            'recipient_name'     => $this->recipient_name,
            'recipient_role'     => $this->recipient_role,
            'sample_ref'         => $this->sample_ref,
            'remarks'            => $this->remarks,
            'recipient_lab_id'   => $this->recipient_lab_id,
            'demand_id'          => $this->demand_id,
            'dispatch_reference' => $this->dispatch_reference,
            'date_of_expiry'     => $this->date_of_expiry,
            'created_at'         => $this->created_at,
        ];
    }
}
