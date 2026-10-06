<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\OnlineOrders\Models\OnlineOrder;

/** Pedido de la tienda online tal como está en la web. */
class OnlineOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'historical' => ['boolean'],
            'web_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:30'],
            'status' => ['required', Rule::in(OnlineOrder::STATUSES)],
            'fulfillment' => ['required', Rule::in(OnlineOrder::FULFILLMENTS)],
            'recipient_name' => ['required', 'string', 'max:255'],
            'document_type' => ['nullable', Rule::in(OnlineOrder::DOCUMENT_TYPES)],
            'document_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'agency' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'gateway' => ['nullable', 'string', 'max:30'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
            'ordered_at' => ['nullable', 'date'],

            'customer' => ['nullable', 'array'],
            'customer.web_id' => ['nullable', 'integer'],
            'customer.code' => ['nullable', 'string', 'max:20'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.document_number' => ['nullable', 'string', 'max:20'],
            'customer.email' => ['nullable', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:30'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.item_type' => ['nullable', 'string', 'max:20'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.subtotal' => ['nullable', 'numeric', 'min:0'],

            'history' => ['nullable', 'array'],
            'history.*.status' => ['required', 'string', 'max:30'],
            'history.*.note' => ['nullable', 'string', 'max:2000'],
            'history.*.internal' => ['boolean'],
            'history.*.user_name' => ['nullable', 'string', 'max:255'],
            'history.*.happened_at' => ['required', 'date'],
        ];
    }
}
