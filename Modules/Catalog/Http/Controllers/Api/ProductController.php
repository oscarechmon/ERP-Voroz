<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api;

use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Http\Requests\StoreProductRequest;
use Modules\Catalog\Http\Requests\UpdateProductRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\BarcodeService;
use Modules\Catalog\Services\ProductService;

/**
 * API de Productos. Autorización por permisos (middleware en las rutas).
 * Respuestas estandarizadas vía ProductResource + envoltura ApiResponse.
 */
class ProductController extends ApiController
{
    public function __construct(
        private readonly ProductService $service,
        private readonly BarcodeService $barcodes,
    ) {
    }

    /** Listado paginado con búsqueda, filtros y ordenamiento. */
    public function index(Request $request): JsonResponse
    {
        $products = $this->service->list($request->all());

        return $this->ok(ProductResource::collection($products)->response()->getData(true));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->service->create($request->validated() + ['image' => $request->file('image')]);

        return $this->created(new ProductResource($product), 'Producto creado correctamente.');
    }

    public function show(int $product): JsonResponse
    {
        $model = $this->service->find($product, ['category', 'brand', 'unit', 'barcodes']);

        return $this->ok(new ProductResource($model));
    }

    public function update(UpdateProductRequest $request, int $product): JsonResponse
    {
        $model = $this->service->update($product, $request->validated() + ['image' => $request->file('image')]);

        return $this->ok(new ProductResource($model), 'Producto actualizado correctamente.');
    }

    public function destroy(int $product): JsonResponse
    {
        $this->service->delete($product);

        return $this->noContent('Producto eliminado.');
    }

    /** Eliminación masiva (acciones en lote de la tabla). */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $count = $this->service->bulkDelete($ids);

        return $this->ok(['deleted' => $count], "{$count} producto(s) eliminado(s).");
    }

    /** Escaneo por código de barras (POS / pistola lectora USB). */
    public function scan(string $barcode): JsonResponse
    {
        return $this->ok(new ProductResource($this->service->scan($barcode)));
    }

    /** Devuelve la etiqueta (código de barras + QR) del producto para imprimir. */
    public function label(int $product): JsonResponse
    {
        $model = $this->service->find($product);

        return $this->ok([
            'name' => $model->name,
            'code' => $model->code,
            'price' => (float) $model->price,
            'barcode_png' => $this->barcodes->png($model->barcode ?? $model->code, 'EAN13'),
            'qr_png' => $this->barcodes->qr($model->barcode ?? $model->code),
        ]);
    }
}
