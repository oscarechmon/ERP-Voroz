<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\OnlineOrders\Events\OnlineOrderStatusChanged;
use Throwable;

/**
 * Avisa a la web de cada paso del seguimiento de un pedido, para que el
 * cliente lo vea en "Mis pedidos". Se manda cuando la respuesta ya salió; si
 * la web no contesta queda en el log y la web lo recupera al sincronizar.
 */
class OrderStatusNotifier
{
    public function handle(OnlineOrderStatusChanged $event): void
    {
        $token = (string) config('integration.token');
        $url = rtrim((string) config('integration.web_url'), '/');

        if ($token === '' || $url === '') {
            return;
        }

        $code = $event->order->code;
        $payload = [
            'status' => $event->history->status,
            'note' => $event->history->note,
            'user_name' => $event->history->user_name,
            'happened_at' => $event->history->happened_at?->toIso8601String(),
        ];

        app()->terminating(function () use ($token, $url, $code, $payload): void {
            try {
                Http::timeout(10)
                    ->acceptJson()
                    ->withHeaders(['X-Integration-Token' => $token])
                    ->post("{$url}/erp/pedidos/".rawurlencode($code).'/estado', $payload)
                    ->throw();
            } catch (Throwable $e) {
                Log::warning('No se pudo avisar a la web del estado del pedido.', [
                    'pedido' => $code,
                    'estado' => $payload['status'],
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
