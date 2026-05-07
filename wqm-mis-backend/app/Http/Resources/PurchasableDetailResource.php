<?php

namespace App\Http\Resources;

use App\Enums\IssueTypeEnum;
use App\Models\Asset\Asset;
use App\Models\Material\Material;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchasableDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        switch ($this->purchasable_type) {
            case Material::class:
                $purchasableType = IssueTypeEnum::STOCK->value;
                break;
            case Asset::class:
                $purchasableType = IssueTypeEnum::INVENTORY->value;
                break;
        }
        return [
            'id' => $this->id,
            'purchasable_type' => $purchasableType,
            'purchasable_id' => $this->purchasable_id,
            'purchase_order_id' => $this->purchase_order_id,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'purchasable' => $this->purchasable,
        ];

    }
}
