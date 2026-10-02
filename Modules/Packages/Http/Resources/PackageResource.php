<?php

declare(strict_types=1);

namespace Modules\Packages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Packages\Models\Package */
class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'code' => $this->whenLoaded('product', fn () => $this->product?->code),
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'total_sessions' => $this->total_sessions,
            'validity_days' => $this->validity_days,
            'is_active' => $this->is_active,
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])->values()),
            'sold_count' => $this->when(isset($this->customer_packages_count), fn () => (int) $this->customer_packages_count),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
