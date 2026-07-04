<?php

declare(strict_types=1);

namespace Modules\Sales\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Sales\Http\Requests\StoreSaleRequest;
use Modules\Sales\Http\Resources\SaleResource;
use Modules\Sales\Repositories\SaleRepository;
use Modules\Sales\Services\SaleService;
use Modules\Settings\Models\Company;

class SaleController extends ApiController
{
    public function __construct(
        private readonly SaleService $service,
        private readonly SaleRepository $repository,
    ) {
    }

    /** Historial de ventas paginado con filtros. */
    public function index(Request $request): JsonResponse
    {
        $sales = $this->repository->paginate($request->all());

        return $this->ok(SaleResource::collection($sales)->response()->getData(true));
    }

    /** Checkout del POS: procesa la venta completa. */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->service->checkout($request->validated());

        return $this->created(new SaleResource($sale), "Venta {$sale->full_number} registrada.");
    }

    public function show(int $sale): JsonResponse
    {
        $model = $this->repository->findOrFail($sale, ['items', 'payments', 'customer', 'user']);

        return $this->ok(new SaleResource($model));
    }

    /** Genera el ticket/comprobante en PDF (formato 80mm). */
    public function ticket(int $sale): Response
    {
        $model = $this->repository->findOrFail($sale, ['items', 'payments', 'customer', 'user']);
        $company = Company::find($model->company_id) ?? Company::first();

        $pdf = Pdf::loadView('sales::ticket', ['sale' => $model, 'company' => $company])
            ->setPaper([0, 0, 226.77, 600]); // ~80mm de ancho

        return $pdf->download("comprobante-{$model->full_number}.pdf");
    }
}
