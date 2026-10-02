<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validación del checkout del POS. */
class StoreSaleRequest extends FormRequest
{
    /** Medios de pago del POS y de los cobros de saldo. */
    public const METHODS = ['efectivo', 'yape', 'plin', 'transferencia', 'tarjeta'];

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
            // Venta con saldo pendiente (se cobra después). Exige cliente.
            'allow_balance' => ['boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.employee_id' => ['nullable', 'exists:employees,id'],

            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', Rule::in(self::METHODS)],
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
            'customer_id.required' => 'Para dejar saldo pendiente, selecciona el cliente.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->sometimes('customer_id', 'required', fn ($input) => (bool) ($input->allow_balance ?? false));
    }
}
