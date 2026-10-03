<?php

declare(strict_types=1);

namespace Modules\Integration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lo que la web mostraba de un ítem antes de pasar su catálogo al sistema
 * (imagen, descripción, si se publicaba, duración). Llega una sola vez, con
 * `php artisan erp:fichas` en la web.
 */
class WebDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Lo controla el token de integración.
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:5000'],
            'web_published' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:600'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ];
    }
}
