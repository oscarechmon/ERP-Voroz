<?php

declare(strict_types=1);

namespace Modules\Purchases\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validación de la edición de una compra. */
class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'supplier_doc' => ['nullable', 'string', 'max:30'],
            'doc_type' => ['nullable', 'string', 'max:20'],
            'apply_igv' => ['nullable', 'boolean'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.cost' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Selecciona un proveedor.',
            'warehouse_id.required' => 'Selecciona el almacén de destino.',
            'items.required' => 'Agrega al menos un producto a la compra.',
        ];
    }
}
