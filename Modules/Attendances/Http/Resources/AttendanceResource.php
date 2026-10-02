<?php

declare(strict_types=1);

namespace Modules\Attendances\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \Modules\Attendances\Models\Attendance */
class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attended_at' => $this->attended_at?->toDateString(),
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'employee_id' => $this->employee_id,
            'appointment_id' => $this->appointment_id,
            'customer_package_id' => $this->customer_package_id,
            'session_number' => $this->session_number,
            'observations' => $this->observations,
            'measurements' => $this->measurements,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'code' => $this->customer->code,
                'name' => $this->customer->name,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => $this->service?->name),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'package' => $this->whenLoaded('customerPackage', fn () => $this->customerPackage ? [
                'id' => $this->customerPackage->id,
                'name' => $this->customerPackage->package_name,
                'total_sessions' => $this->customerPackage->total_sessions,
            ] : null),
            'supplies' => $this->whenLoaded('supplies', fn () => $this->supplies->map(fn ($s) => [
                'product_id' => $s->product_id,
                'name' => $s->product?->name,
                'quantity' => (float) $s->quantity,
            ])->values()),
            'commission' => $this->whenLoaded('commission', fn () => $this->commission ? [
                'amount' => (float) $this->commission->amount,
                'status' => $this->commission->status,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
