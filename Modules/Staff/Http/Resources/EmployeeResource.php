<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Staff\Models\Employee */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'position' => $this->position,
            'phone' => $this->phone,
            'doc_number' => $this->doc_number,
            'is_active' => $this->is_active,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
