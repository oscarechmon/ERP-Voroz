<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\OnlineOrders\Models\OnlineOrder;

/** @mixin \Modules\OnlineOrders\Models\OnlineOrder */
class OnlineOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'next_statuses' => $this->nextStatuses(),
            'fulfillment' => $this->fulfillment,
            'fulfillment_label' => OnlineOrder::fulfillmentLabel((string) $this->fulfillment),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? $this->customer_name,
            'customer_email' => $this->customer_email,
            'recipient_name' => $this->recipient_name,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'phone' => $this->phone,
            'address' => $this->address,
            'district' => $this->district,
            'reference' => $this->reference,
            'department' => $this->department,
            'province' => $this->province,
            'agency' => $this->agency,
            'notes' => $this->notes,
            'subtotal' => (float) $this->subtotal,
            'delivery_fee' => (float) $this->delivery_fee,
            'total' => (float) $this->total,
            'gateway' => $this->gateway,
            'payment_reference' => $this->payment_reference,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'ordered_at' => ($this->ordered_at ?? $this->created_at)?->toIso8601String(),
            'sale' => $this->whenLoaded('sale', fn () => $this->sale ? [
                'id' => $this->sale->id,
                'full_number' => $this->sale->full_number,
                'status' => $this->sale->status,
            ] : null),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'product_id' => $i->product_id,
                'item_type' => $i->item_type,
                'name' => $i->name,
                'unit_price' => (float) $i->unit_price,
                'quantity' => (float) $i->quantity,
                'subtotal' => (float) $i->subtotal,
            ])->values()),
            'histories' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($h) => [
                'status' => $h->status,
                'note' => $h->note,
                'internal' => $h->internal,
                'user_name' => $h->user_name,
                'happened_at' => $h->happened_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
