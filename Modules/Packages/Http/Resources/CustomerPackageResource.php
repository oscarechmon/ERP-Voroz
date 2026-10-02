<?php

declare(strict_types=1);

namespace Modules\Packages\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Packages\Models\CustomerPackage */
class CustomerPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
            ] : null),
            'package_id' => $this->package_id,
            'package_name' => $this->package_name,
            'sale_id' => $this->sale_id,
            'price' => (float) $this->price,
            'total_sessions' => $this->total_sessions,
            'used_sessions' => $this->used_sessions,
            'remaining_sessions' => $this->remainingSessions(),
            'purchased_at' => $this->purchased_at?->toDateString(),
            'expires_at' => $this->expires_at?->toDateString(),
            // Un activo con la vigencia vencida se muestra como vencido aunque nadie lo haya tocado.
            'status' => $this->status === 'active' && $this->isExpired() ? 'expired' : $this->status,
            'sessions' => $this->whenLoaded('sessions', fn () => $this->sessions->map(fn ($s) => [
                'session_number' => $s->session_number,
                'attendance_id' => $s->attendance_id,
                'consumed_at' => $s->consumed_at?->toIso8601String(),
            ])->values()),
        ];
    }
}
