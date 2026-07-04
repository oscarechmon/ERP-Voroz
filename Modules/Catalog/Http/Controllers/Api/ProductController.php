<?php

declare(strict_types=1);

namespace Modules\Catalog\Http\Controllers\Api;

use App\Core\Exceptions\BusinessException;
use App\Core\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Catalog\Exports\ProductsExport;
use Modules\Catalog\Http\Requests\StoreProductRequest;
use Modules\Catalog\Http\Requests\UpdateProductRequest;
use Modules\Catalog\Http\Resources\ProductResource;
use Modules\Catalog\Services\BarcodeService;
use Modules\Catalog\Services\ProductService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    /** Exporta el catálogo (respetando el buscador) a Excel o CSV. */
    public function export(Request $request): BinaryFileResponse
    {
        $products = $this->service->all($request->all())->load(['category', 'brand', 'unit']);
        $format = strtolower((string) $request->query('export', 'xlsx'));
        $filename = 'productos-' . now()->format('Ymd_His');

        return match ($format) {
            'xlsx' => Excel::download(new ProductsExport($products), "{$filename}.xlsx", ExcelWriter::XLSX),
            'csv' => Excel::download(new ProductsExport($products), "{$filename}.csv", ExcelWriter::CSV),
            default => throw new BusinessException('Formato no soportado. Usa xlsx o csv.'),
        };
    }

    /** Devuelve la etiqueta (código de barras + QR) del producto para imprimir. */
    public function label(int $product): JsonResponse
    {
        $model = $this->service->find($product);
        $value = $model->barcode ?? $model->code;
        // EAN-13 sólo si el valor es numérico de 13 dígitos; si no, CODE128 (siempre válido).
        $type = preg_match('/^\d{13}$/', (string) $value) === 1 ? 'EAN13' : 'CODE128';

        return $this->ok([
            'name' => $model->name,
            'code' => $model->code,
            'price' => (float) $model->price,
            'barcode_png' => $this->barcodes->png($value, $type),
            'qr_png' => $this->barcodes->qr($value),
        ]);
    }
}
