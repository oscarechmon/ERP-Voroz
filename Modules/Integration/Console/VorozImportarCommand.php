<?php

declare(strict_types=1);

namespace Modules\Integration\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Integration\Services\SqlDumpReader;
use Modules\Integration\Services\VorozImporter;
use Throwable;

/**
 * Importa clientes y ventas del ERP anterior (voroz) desde el volcado SQL de
 * su base (exportado con phpMyAdmin). El archivo no se guarda en el
 * repositorio: tiene datos personales. Se sube al servidor, se importa y se
 * borra.
 *
 *   php artisan voroz:importar storage/app/private/u257283941_voroz.sql --simular
 *   php artisan voroz:importar storage/app/private/u257283941_voroz.sql
 */
class VorozImportarCommand extends Command
{
    protected $signature = 'voroz:importar
        {archivo : Ruta del volcado .sql de voroz}
        {--simular : Muestra lo que haría sin guardar nada}';

    protected $description = 'Importa clientes y ventas del ERP anterior (voroz) desde su volcado SQL, sin mover stock';

    public function handle(VorozImporter $importer): int
    {
        $path = (string) $this->argument('archivo');
        $path = is_file($path) ? $path : base_path($path);
        $simulate = (bool) $this->option('simular');

        try {
            $dump = new SqlDumpReader($path);

            DB::beginTransaction();
            $result = $importer->import($dump);
            $simulate ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->error('No se importó nada: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($simulate ? 'SIMULACIÓN (no se guardó nada):' : 'Importado:');
        $this->line("  Clientes: {$result['customers']['created']} nuevos, {$result['customers']['linked']} ya estaban (se completaron sus datos vacíos).");
        $s = $result['sales'];
        $this->line("  Ventas: {$s['imported']} importadas ({$s['completed']} vigentes por S/ ".number_format($s['total'], 2).", {$s['cancelled']} anuladas), {$s['skipped']} ya estaban.");

        $this->line('  Productos:');
        foreach ($result['products'] as $from => $to) {
            $this->line("    {$from} → {$to}");
        }

        foreach ($result['renumbered'] as $change) {
            $this->warn("  Número ya usado aquí, se guardó como: {$change}");
        }

        foreach ($result['series'] as $series => $number) {
            $this->line("  La serie {$series} sigue desde el {$number}.");
        }

        $this->line('  El stock no se tocó: estas ventas son historia.');

        if ($simulate) {
            $this->comment('Si está bien, vuelve a ejecutarlo sin --simular.');
        } else {
            $this->comment('Listo. Borra el archivo .sql del servidor: tiene datos personales.');
        }

        return self::SUCCESS;
    }
}
