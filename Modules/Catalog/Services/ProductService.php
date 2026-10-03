<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Repositories\Contracts\ProductRepositoryInterface;

/**
 * Lógica de negocio de productos: correlativo de código interno, generación
 * automática de código de barras, procesamiento de imagen y escaneo por barras.
 */
class ProductService extends BaseService
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly BarcodeService $barcodes,
    ) {
        parent::__construct($products);
    }

    public function create(array $data): Model
    {
        return DB::transaction(function () use ($data): Product {
            $data['code'] = $data['code'] ?? $this->products->nextCode();
            $data['company_id'] = $data['company_id'] ?? optional(auth()->user())->company_id;

            $image = $data['image'] ?? null;
            unset($data['image']);

            /** @var Product $product */
            $product = $this->products->create($data);

            // Genera un EAN-13 válido si no se proporcionó código de barras.
            if (empty($product->barcode)) {
                $product->barcode = $this->barcodes->generateEan13($product->id);
                $product->saveQuietly();
            }

            if ($image instanceof UploadedFile) {
                $product->image_path = $this->storeImage($image, $product->id);
                $product->saveQuietly();
            }

            return $product->fresh();
        });
    }

    public function update(int|string $id, array $data): Model
    {
        $this->guardPackage($id);

        $image = $data['image'] ?? null;
        unset($data['image']);

        /** @var Product $product */
        $product = $this->products->update($id, $data);

        if ($image instanceof UploadedFile) {
            $this->replaceImage($product, $image);
        }

        return $product->fresh();
    }

    /**
     * Cambia la imagen del producto (borra la anterior). Se guarda sin pasar
     * por los eventos, como al crearlo: quien llama decide si avisar.
     */
    public function replaceImage(Product $product, UploadedFile $image): void
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->image_path = $this->storeImage($image, $product->id);
        $product->saveQuietly();
    }

    public function delete(int|string $id): bool
    {
        $this->guardPackage($id);

        return parent::delete($id);
    }

    public function bulkDelete(array $ids): int
    {
        if (Product::whereIn('id', $ids)->where('type', Product::TYPE_PACKAGE)->exists()) {
            throw new BusinessException('Los paquetes se eliminan desde Paquetes.');
        }

        return parent::bulkDelete($ids);
    }

    /** El producto de un paquete lo mantiene el módulo Paquetes (sesiones, servicios, vigencia). */
    private function guardPackage(int|string $id): void
    {
        if (Product::whereKey($id)->where('type', Product::TYPE_PACKAGE)->exists()) {
            throw new BusinessException('Este ítem es un paquete: edítalo desde Paquetes.');
        }
    }

    /** Busca un producto por código de barras (para el POS / pistola lectora). */
    public function scan(string $barcode): Product
    {
        $product = $this->products->findByBarcode($barcode);

        if (! $product) {
            throw new BusinessException("No se encontró un producto con el código «{$barcode}».", 404);
        }

        return $product->load(['category:id,name', 'brand:id,name', 'unit:id,name,abbreviation']);
    }

    /**
     * Redimensiona (máx. 800px) y almacena la imagen del producto en el disco
     * público, devolviendo su ruta relativa.
     */
    private function storeImage(UploadedFile $file, int $productId): string
    {
        $manager = new ImageManager(\Intervention\Image\Drivers\Gd\Driver::class);
        $image = $manager->read($file->getRealPath())->scaleDown(width: 800);

        $path = "products/{$productId}/" . uniqid('img_') . '.webp';
        Storage::disk('public')->put($path, (string) $image->toWebp(80));

        return $path;
    }
}
