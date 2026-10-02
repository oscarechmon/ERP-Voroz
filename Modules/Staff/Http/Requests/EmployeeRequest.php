<?php

declare(strict_types=1);

namespace Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('employee');

        return [
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'doc_number' => ['nullable', 'string', 'max:20'],
            'user_id' => ['nullable', 'exists:users,id', Rule::unique('employees', 'user_id')->ignore($id)->whereNull('deleted_at')],
            'is_active' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', Rule::exists('products', 'id')->where('type', Product::TYPE_SERVICE)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'user_id.unique' => 'Ese usuario ya está enlazado a otro empleado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}
