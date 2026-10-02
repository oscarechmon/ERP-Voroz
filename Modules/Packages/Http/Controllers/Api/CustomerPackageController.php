<?php

declare(strict_types=1);

namespace Modules\Packages\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Modules\Packages\Http\Resources\CustomerPackageResource;
use Modules\Packages\Models\Package;
use Modules\Packages\Repositories\CustomerPackageRepository;
use Modules\Packages\Services\CustomerPackageService;

/** Paquetes de los clientes: consulta del saldo de sesiones y asignación manual. */
class CustomerPackageController extends ApiController
{
    public function __construct(
        private readonly CustomerPackageRepository $repository,
        private readonly CustomerPackageService $service,
    ) {}

    /** Listado; `all=1` sin paginar (p. ej. los usables de un cliente para una atención). */
    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('all')) {
            return $this->ok(CustomerPackageResource::collection($this->repository->all($request->all())));
        }

        return $this->ok(CustomerPackageResource::collection($this->repository->paginate($request->all()))->response()->getData(true));
    }

    public function show(int $customerPackage): JsonResponse
    {
        return $this->ok(new CustomerPackageResource($this->repository->findOrFail($customerPackage, ['sessions'])));
    }

    /**
     * Asigna un paquete sin pasar por el POS (cortesía, canje). No crea venta
     * ni cobro: lo vendido se registra en el punto de venta.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'package_id' => ['required', 'exists:packages,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'purchased_at' => ['nullable', 'date'],
        ], [], ['customer_id' => 'cliente', 'package_id' => 'paquete']);

        $package = Package::findOrFail($data['package_id']);
        $assigned = $this->service->assign(
            $package,
            (int) $data['customer_id'],
            isset($data['price']) ? (float) $data['price'] : null,
            null,
            isset($data['purchased_at']) ? Carbon::parse($data['purchased_at']) : null,
        );

        return $this->created(new CustomerPackageResource($assigned->load('customer', 'package')), 'Paquete asignado.');
    }
}
