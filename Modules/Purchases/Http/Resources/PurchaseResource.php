<?php

declare(strict_types=1);

namespace Modules\Purchases\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Purchases\Models\Purchase */
class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'warehouse_id' => $this->warehouse_id,
            'supplier_doc' => $this->supplier_doc,
            'doc_type' => $this->doc_type,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'tax_percent' => (float) $this->tax_percent,
            'apply_igv' => (float) $this->tax_percent > 0,
            'status' => $this->status,
            'notes' => $this->notes,
            'purchased_at' => $this->purchased_at?->toIso8601String(),
            'supplier' => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id' => $this->supplier->id,
                'name' => $this->supplier->name,
                'doc_number' => $this->supplier->doc_number,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'description' => $i->description,
                'quantity' => (float) $i->quantity,
                'cost' => (float) $i->cost,
                'subtotal' => (float) $i->subtotal,
            ])),
        ];
    }
}
