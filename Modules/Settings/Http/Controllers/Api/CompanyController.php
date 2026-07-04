<?php

declare(strict_types=1);

namespace Modules\Settings\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Modules\Settings\Http\Requests\CompanyRequest;
use Modules\Settings\Http\Resources\CompanyResource;
use Modules\Settings\Models\Company;

/** Datos de la empresa (registro único que usa todo el ERP). */
class CompanyController extends ApiController
{
    public function show(): JsonResponse
    {
        $company = Company::firstOrFail();

        return $this->ok(new CompanyResource($company));
    }

    public function update(CompanyRequest $request): JsonResponse
    {
        $company = Company::firstOrFail();
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }
            $data['logo_path'] = $this->storeLogo($request->file('logo'), $company->id);
        }
        unset($data['logo']);

        $company->update($data);

        return $this->ok(new CompanyResource($company->fresh()), 'Datos de la empresa actualizados.');
    }

    private function storeLogo($file, int $companyId): string
    {
        $manager = new ImageManager(Driver::class);
        $image = $manager->read($file->getRealPath())->scaleDown(width: 400);
        $path = "company/{$companyId}/logo_" . uniqid() . '.webp';
        Storage::disk('public')->put($path, (string) $image->toWebp(85));

        return $path;
    }
}
