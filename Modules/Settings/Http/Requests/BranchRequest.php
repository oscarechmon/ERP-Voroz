<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validación de sucursales. */
class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_main' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ['name.required' => 'El nombre de la sucursal es obligatorio.'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_main' => $this->boolean('is_main'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
