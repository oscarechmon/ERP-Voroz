<?php

declare(strict_types=1);

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representación JSON de un usuario para la API.
 * Incluye sus roles y permisos efectivos para que el frontend construya la UI
 * y aplique el control de acceso (menús/botones) sin llamadas extra.
 *
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'is_active' => $this->is_active,
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
