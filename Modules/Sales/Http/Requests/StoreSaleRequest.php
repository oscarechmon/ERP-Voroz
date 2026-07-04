<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validación del checkout del POS. */
class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'doc_type' => ['required', Rule::in(['ticket', 'boleta', 'factura', 'cotizacion'])],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', Rule::in(['efectivo', 'yape', 'plin', 'transferencia', 'tarjeta'])],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto a la venta.',
            'items.min' => 'Agrega al menos un producto a la venta.',
            'doc_type.required' => 'Selecciona el tipo de comprobante.',
        ];
    }
}
