<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Arr;
use Modules\Contacts\Models\Customer;
use Modules\Settings\Models\Company;

/**
 * Encuentra o crea el cliente que corresponde a uno de la web: primero por su
 * enlace, después por documento (DNI/RUC) y por correo. Así un cliente que ya
 * compró en la tienda no se duplica al importarse su ficha.
 */
class CustomerResolver
{
    /** Campos de la ficha que la web comparte. */
    private const PROFILE = [
        'phone', 'whatsapp', 'address', 'district', 'birth_date', 'gender', 'how_knew', 'notes',
        'allergies', 'restrictions', 'contraindications', 'medications', 'relevant_info',
    ];

    public function __construct(private readonly WebLinks $links) {}

    /**
     * @param  array<string, mixed>  $data  name, document_number, email + campos de la ficha;
     *                                      web_id y code si es un cliente de la web.
     * @param  bool  $update  Completar/actualizar la ficha si ya existía.
     */
    public function resolve(array $data, bool $update = false): ?Customer
    {
        if (empty($data['name'])) {
            return null;
        }

        $raw = mb_substr(trim((string) ($data['document_number'] ?? '')), 0, 20);
        $digits = preg_replace('/\D/', '', $raw);
        // DNI u RUC por su largo; cualquier otro documento se guarda tal cual
        // como carné de extranjería (se corrige en la ficha si fuera pasaporte).
        [$docType, $document] = match (true) {
            strlen($digits) === 8 && strlen($raw) === 8 => ['DNI', $digits],
            strlen($digits) === 11 && strlen($raw) === 11 => ['RUC', $digits],
            $raw !== '' => ['CE', $raw],
            default => [null, ''],
        };
        $email = trim((string) ($data['email'] ?? '')) ?: null;

        $customer = (! empty($data['web_id']) && ($id = $this->links->id('customer', $data['web_id'])) ? Customer::withTrashed()->find($id) : null)
            ?? ($docType ? Customer::where('doc_number', $document)->first() : null)
            ?? ($email ? Customer::where('email', $email)->first() : null);

        $profile = array_filter(Arr::only($data, self::PROFILE), fn ($v) => $v !== null && $v !== '');

        if (! $customer) {
            $customer = Customer::create([
                'company_id' => Company::query()->value('id'),
                // El código de la web se conserva si no está tomado aquí.
                'code' => ! empty($data['code']) && ! Customer::withTrashed()->where('code', $data['code'])->exists() ? $data['code'] : null,
                'doc_type' => $docType ?? 'DNI',
                'doc_number' => $docType ? $document : null,
                'name' => $data['name'],
                'email' => $email,
                'is_active' => (bool) ($data['active'] ?? true),
            ] + $profile + ['notes' => empty($data['web_id']) ? 'Cliente de la tienda online' : null]);
        } elseif ($update) {
            $customer->fill(array_filter([
                'name' => $data['name'],
                'email' => $customer->email ?: $email,
                'doc_type' => $customer->doc_number ? null : $docType,
                'doc_number' => $customer->doc_number ?: ($docType ? $document : null),
            ]) + $profile)->save();
        }

        if (! empty($data['web_id'])) {
            $this->links->link('customer', $data['web_id'], $customer);
        }

        return $customer;
    }
}
