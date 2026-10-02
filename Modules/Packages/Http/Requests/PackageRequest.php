<?php

declare(strict_types=1);

namespace Modules\Packages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0'],
            'total_sessions' => ['required', 'integer', 'min:1', 'max:1000'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'is_active' => ['boolean'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', Rule::exists('products', 'id')->where('type', Product::TYPE_SERVICE)],
        ];
    }

    public function messages(): array
    {
        return [
            'service_ids.required' => 'Elige al menos un servicio incluido.',
            'service_ids.min' => 'Elige al menos un servicio incluido.',
            'total_sessions.min' => 'El paquete debe tener al menos una sesión.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}
