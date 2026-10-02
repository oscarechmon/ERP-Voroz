<?php

declare(strict_types=1);

namespace Modules\Attendances\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Catalog\Models\Product;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'service_id' => ['required', Rule::exists('products', 'id')->where('type', Product::TYPE_SERVICE)],
            'employee_id' => ['required', 'exists:employees,id'],
            'appointment_id' => ['nullable', 'exists:appointments,id'],
            'customer_package_id' => ['nullable', 'exists:customer_packages,id'],
            'attended_at' => ['required', 'date', 'before_or_equal:today'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'measurements' => ['nullable', 'string', 'max:2000'],
            'supplies' => ['nullable', 'array'],
            'supplies.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')->where('type', Product::TYPE_PRODUCT)],
            'supplies.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'attended_at.before_or_equal' => 'La atención no puede tener fecha futura.',
            'supplies.*.product_id.distinct' => 'Un insumo está repetido.',
        ];
    }
}
