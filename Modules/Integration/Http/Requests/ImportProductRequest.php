<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;

/** Ítem que la web ya tenía y se da de alta en el sistema al conectarlas. */
class ImportProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Lo controla el token de integración.
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(Product::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:50'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'stock_min' => ['nullable', 'numeric', 'min:0'],
            'active' => ['boolean'],
        ];
    }
}
