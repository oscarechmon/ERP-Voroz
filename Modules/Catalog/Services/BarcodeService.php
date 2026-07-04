<?php

declare(strict_types=1);

namespace Modules\Catalog\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Generación de códigos de barras (EAN13/CODE128) y QR.
 *
 * Devuelve data-URIs listos para <img> en el frontend (impresión de etiquetas)
 * y también genera un EAN13 válido con dígito verificador para nuevos productos.
 */
class BarcodeService
{
    /** Devuelve un PNG en base64 (data-URI) del código de barras. */
    public function png(string $value, string $type = 'CODE128'): string
    {
        $generator = new BarcodeGeneratorPNG();
        $code = $this->resolveType($type, $generator);
        $data = $generator->getBarcode($value, $code, 2, 60);

        return 'data:image/png;base64,' . base64_encode($data);
    }

    /** Devuelve un SVG (útil para impresión nítida de etiquetas). */
    public function svg(string $value, string $type = 'CODE128'): string
    {
        $generator = new BarcodeGeneratorSVG();
        $code = $this->resolveType($type, $generator);

        return $generator->getBarcode($value, $code, 2, 60);
    }

    /** Devuelve un QR en base64 (data-URI). */
    public function qr(string $value, int $size = 200): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($value)
            ->size($size)
            ->margin(8)
            ->build();

        return $result->getDataUri();
    }

    /**
     * Genera un código EAN-13 válido a partir de un número base (p. ej. el id del
     * producto), calculando el dígito verificador estándar.
     */
    public function generateEan13(int $seed): string
    {
        // Prefijo interno "200" (rango reservado para uso interno) + 9 dígitos.
        $base = '200' . str_pad((string) $seed, 9, '0', STR_PAD_LEFT);
        $base = substr($base, 0, 12);

        return $base . $this->ean13CheckDigit($base);
    }

    private function ean13CheckDigit(string $digits12): string
    {
        $sum = 0;
        foreach (str_split($digits12) as $i => $digit) {
            $sum += ((int) $digit) * (($i % 2 === 0) ? 1 : 3);
        }
        $check = (10 - ($sum % 10)) % 10;

        return (string) $check;
    }

    /** Mapea el tipo lógico a la constante del generador de picqer. */
    private function resolveType(string $type, object $generator): string
    {
        return match (strtoupper($type)) {
            'EAN13' => $generator::TYPE_EAN_13,
            'CODE39' => $generator::TYPE_CODE_39,
            default => $generator::TYPE_CODE_128,
        };
    }
}
