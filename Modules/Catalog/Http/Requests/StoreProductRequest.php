<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;

/** Validación para crear un producto. */
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización se controla por middleware de permiso en la ruta.
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('products', 'code')],
            'barcode' => ['nullable', 'string', 'max:50'],
            'sku' => ['nullable', 'string', 'max:50'],
            'type' => ['required', Rule::in(Product::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
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
            'type.in' => 'El tipo debe ser producto o servicio.',
            'name.required' => 'El nombre del producto es obligatorio.',
            'code.unique' => 'Ya existe un producto con este código interno.',
            'cost.required' => 'El costo es obligatorio.',
            'price.required' => 'El precio de venta es obligatorio.',
            'price.min' => 'El precio no puede ser negativo.',
            'image.max' => 'La imagen no debe superar los 4 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliza los booleanos que llegan como "true"/"1"/on desde el formulario.
        $this->merge([
            'type' => $this->input('type', Product::TYPE_PRODUCT),
            'track_stock' => $this->boolean('track_stock', true),
            'has_expiry' => $this->boolean('has_expiry'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
