<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Sales\Models\SaleItem */
class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'tax' => (float) $this->tax,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
