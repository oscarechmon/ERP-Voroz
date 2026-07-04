<?php

declare(strict_types=1);

namespace Modules\Cashbox\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Cashbox\Models\CashSession */
class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'register' => $this->whenLoaded('register', fn () => $this->register?->name),
            'user' => $this->whenLoaded('user', fn () => $this->user?->name),
            'opening_amount' => (float) $this->opening_amount,
            'cash_sales' => (float) $this->cash_sales,
            'income' => (float) $this->income,
            'expense' => (float) $this->expense,
            'expected_amount' => $this->expected_amount !== null ? (float) $this->expected_amount : null,
            'counted_amount' => $this->counted_amount !== null ? (float) $this->counted_amount : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'status' => $this->status,
            'notes' => $this->notes,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'movements' => $this->whenLoaded('movements', fn () => $this->movements->map(fn ($mv) => [
                'id' => $mv->id,
                'type' => $mv->type,
                'amount' => (float) $mv->amount,
                'reason' => $mv->reason,
                'created_at' => $mv->created_at?->toIso8601String(),
            ])),
        ];
    }
}
