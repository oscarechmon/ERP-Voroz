<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;

/** Validación para actualizar un producto (código único ignorando el actual). */
class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('products', 'code')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:50'],
            'type' => ['sometimes', Rule::in(Product::TYPES)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'offer_price' => ['nullable', 'numeric', 'min:0'],
            'stock_min' => ['nullable', 'numeric', 'min:0'],
            'stock_max' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'has_expiry' => ['boolean'],
            'is_active' => ['boolean'],
            'web_published' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'duration_minutes.min' => 'La duración debe ser de al menos 5 minutos.',
            'duration_minutes.max' => 'La duración no puede pasar de 600 minutos.',
            'image.max' => 'La imagen no debe superar los 4 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'track_stock' => $this->boolean('track_stock', true),
            'has_expiry' => $this->boolean('has_expiry'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
