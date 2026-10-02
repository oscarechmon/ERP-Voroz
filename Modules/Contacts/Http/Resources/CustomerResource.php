<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Contacts\Models\Customer */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'doc_type' => $this->doc_type,
            'doc_number' => $this->doc_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'district' => $this->district,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'how_knew' => $this->how_knew,
            'notes' => $this->notes,
            'allergies' => $this->allergies,
            'restrictions' => $this->restrictions,
            'contraindications' => $this->contraindications,
            'medications' => $this->medications,
            'relevant_info' => $this->relevant_info,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
