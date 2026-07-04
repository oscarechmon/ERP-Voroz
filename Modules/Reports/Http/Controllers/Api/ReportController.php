<?php

declare(strict_types=1);

namespace Modules\Reports\Http\Controllers\Api;

use App\Core\Exceptions\BusinessException;
use App\Core\Http\Controllers\ApiController;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Reports\Exports\ArrayExport;
use Modules\Reports\Services\ReportService;
use Symfony\Component\HttpFoundation\Response;

/**
 * API de reportes. Un mismo endpoint sirve los datos en JSON o los exporta a
 * Excel, CSV o PDF según el parámetro `export`.
 */
class ReportController extends ApiController
{
    /** Tipos de reporte soportados y su método en el servicio. */
    private const TYPES = ['sales', 'profit', 'stock', 'sellers', 'purchases'];

    public function __construct(private readonly ReportService $service)
    {
    }

    public function show(string $type, Request $request): Response
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new BusinessException("Reporte «{$type}» no existe.", 404);
        }

        $from = $request->query('from');
        $to = $request->query('to');

        $report = match ($type) {
            'stock' => $this->service->stock(),
            'sales' => $this->service->sales($from, $to),
            'profit' => $this->service->profit($from, $to),
            'sellers' => $this->service->sellers($from, $to),
            'purchases' => $this->service->purchases($from, $to),
        };

        $export = $request->query('export');
        if ($export) {
            return $this->export($report, $export);
        }

        return $this->ok([
            'title' => $report['title'],
            'headings' => $report['headings'],
            'rows' => $report['rows'],
            'summary' => $report['summary'],
        ]);
    }

    /** Materializa el reporte en el formato solicitado. */
    private function export(array $report, string $format): Response
    {
        $slug = Str::slug($report['title']);
        $filename = "{$slug}-" . now()->format('Ymd_His');

        return match ($format) {
            'xlsx' => Excel::download(new ArrayExport($report['rows'], $report['headings'], $report['title']), "{$filename}.xlsx", ExcelWriter::XLSX),
            'csv' => Excel::download(new ArrayExport($report['rows'], $report['headings'], $report['title']), "{$filename}.csv", ExcelWriter::CSV),
            'pdf' => Pdf::loadView('reports::table', [
                'title' => $report['title'],
                'headings' => $report['headings'],
                'rows' => $report['rows'],
                'summary' => $report['summary'],
                'generatedAt' => now()->format('d/m/Y H:i'),
            ])->setPaper('a4', 'landscape')->download("{$filename}.pdf"),
            default => throw new BusinessException('Formato de exportación no soportado. Usa xlsx, csv o pdf.'),
        };
    }
}
