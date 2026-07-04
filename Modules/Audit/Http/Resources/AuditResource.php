<?php

declare(strict_types=1);

namespace Modules\Audit\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \OwenIt\Auditing\Models\Audit */
class AuditResource extends JsonResource
{
    /** Etiquetas legibles de los eventos de auditoría. */
    private const EVENTS = [
        'created' => 'Creó',
        'updated' => 'Actualizó',
        'deleted' => 'Eliminó',
        'restored' => 'Restauró',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'event_label' => self::EVENTS[$this->event] ?? $this->event,
            'model' => class_basename($this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'user' => $this->whenLoaded('user', fn () => $this->user?->name ?? 'Sistema'),
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'url' => $this->url,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
