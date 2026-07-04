<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validación de los datos de la empresa. */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'ruc' => ['required', 'digits:11'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:3'],
            'currency_symbol' => ['required', 'string', 'max:5'],
            'igv_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'prices_include_igv' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'business_name.required' => 'La razón social es obligatoria.',
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.digits' => 'El RUC debe tener 11 dígitos.',
            'igv_percent.required' => 'El porcentaje de IGV es obligatorio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['prices_include_igv' => $this->boolean('prices_include_igv', true)]);
    }
}
