<?php

declare(strict_types=1);

namespace Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validación de clientes con reglas de documento peruano. */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::in(['DNI', 'RUC', 'CE', 'PASAPORTE'])],
            'doc_number' => [
                'nullable', 'string', 'max:20',
                Rule::when($this->input('doc_type') === 'DNI', ['digits:8']),
                Rule::when($this->input('doc_type') === 'RUC', ['digits:11']),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre o razón social es obligatorio.',
            'doc_number.digits' => 'El número de documento no tiene la longitud correcta para el tipo seleccionado.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'doc_type' => $this->input('doc_type', 'DNI'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
