<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Sales\Models\Sale;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doc_type' => $this->doc_type,
            'channel' => $this->channel,
            'external_reference' => $this->external_reference,
            'full_number' => $this->full_number,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'discount' => (float) $this->discount,
            'total' => (float) $this->total,
            'tax_percent' => (float) $this->tax_percent,
            'paid' => (float) $this->paid,
            'change' => (float) $this->change,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'notes' => $this->notes,
            'sold_at' => $this->sold_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'cancelled_by' => $this->whenLoaded('canceller', fn () => $this->canceller?->name),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'doc_number' => $this->customer->doc_number,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'method' => $p->method,
                'amount' => (float) $p->amount,
                'reference' => $p->reference,
            ])),
        ];
    }
}
