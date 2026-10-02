<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pedido pagado en la tienda online. Los precios son los que cobró la web: el
 * cliente ya pagó ese monto, así que la venta los respeta.
 */
class WebSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Lo controla el token de integración.
    }

    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'max:50'],
            'customer' => ['nullable', 'array'],
            'customer.web_id' => ['nullable', 'integer'],
            'customer.code' => ['nullable', 'string', 'max:20'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.document_number' => ['nullable', 'string', 'max:20'],
            'customer.email' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'string', 'max:20'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
