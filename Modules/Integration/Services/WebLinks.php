<?php

declare(strict_types=1);

namespace Modules\Integration\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Integration\Models\IntegrationReference;

/**
 * Enlaces entre los registros de la web y los de aquí: `web:{tipo}:{id}` →
 * modelo. Con ellos la importación es repetible y cada tipo encuentra lo que
 * se importó antes (la venta a su cliente, la atención a su paquete…).
 */
class WebLinks
{
    /** @var array<string, int|null> */
    private array $cache = [];

    public static function reference(string $type, int|string $webId): string
    {
        return "web:{$type}:{$webId}";
    }

    /** Id local enlazado, o null si ese registro de la web aún no llegó. */
    public function id(string $type, int|string|null $webId): ?int
    {
        if ($webId === null || $webId === '') {
            return null;
        }

        $reference = self::reference($type, $webId);

        if (! array_key_exists($reference, $this->cache)) {
            $id = IntegrationReference::where('reference', $reference)->value('model_id');
            $this->cache[$reference] = $id !== null ? (int) $id : null;
        }

        return $this->cache[$reference];
    }

    public function link(string $type, int|string $webId, Model $model): void
    {
        $reference = self::reference($type, $webId);

        IntegrationReference::updateOrCreate(
            ['reference' => $reference],
            ['kind' => IntegrationReference::KIND_LINK, 'model_type' => $model::class, 'model_id' => $model->getKey()],
        );

        $this->cache[$reference] = (int) $model->getKey();
    }
}
