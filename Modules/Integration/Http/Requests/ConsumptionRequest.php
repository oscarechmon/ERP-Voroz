<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Insumos usados en una atención de la web. */
class ConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Lo controla el token de integración.
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
