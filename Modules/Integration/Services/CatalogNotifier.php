<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa a la web de cada producto que cambió (datos o stock), para que su copia
 * del catálogo esté al día sin esperar a una sincronización completa.
 *
 * Junta los cambios de toda la petición y los manda en un solo envío cuando la
 * respuesta ya salió: quien vende en el POS no espera a la web. Si la web no
 * contesta, solo queda en el log; su botón "Sincronizar" lo recupera.
 */
class CatalogNotifier
{
    /** @var array<int, true> */
    private array $pending = [];

    private bool $scheduled = false;

    public function __construct(private readonly CatalogFeed $feed) {}

    public function touch(int $productId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->pending[$productId] = true;

        if (! $this->scheduled) {
            $this->scheduled = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    public function flush(): void
    {
        $ids = array_keys($this->pending);
        $this->pending = [];
        $this->scheduled = false;

        if ($ids === []) {
            return;
        }

        try {
            Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['X-Integration-Token' => (string) config('integration.token')])
                ->post(rtrim((string) config('integration.web_url'), '/').'/erp/catalogo', [
                    'items' => $this->feed->items($ids),
                ])
                ->throw();
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar a la web del cambio de catálogo.', [
                'productos' => $ids,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function enabled(): bool
    {
        return (string) config('integration.token') !== '' && (string) config('integration.web_url') !== '';
    }
}
