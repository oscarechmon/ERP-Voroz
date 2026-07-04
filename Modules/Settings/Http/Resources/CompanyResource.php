<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \Modules\Settings\Models\Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'trade_name' => $this->trade_name,
            'ruc' => $this->ruc,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'logo_url' => $this->logo_path ? Storage::url($this->logo_path) : null,
            'currency' => $this->currency,
            'currency_symbol' => $this->currency_symbol,
            'igv_percent' => (float) $this->igv_percent,
            'prices_include_igv' => $this->prices_include_igv,
            'is_active' => $this->is_active,
        ];
    }
}
