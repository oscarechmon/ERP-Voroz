<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Inventory\Models\Stock */
class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'quantity' => (float) $this->quantity,
            'avg_cost' => (float) $this->avg_cost,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
                'stock_min' => (float) $this->product->stock_min,
                'price' => (float) $this->product->price,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'is_low' => $this->relationLoaded('product')
                ? (float) $this->quantity <= (float) $this->product->stock_min
                : null,
        ];
    }
}
