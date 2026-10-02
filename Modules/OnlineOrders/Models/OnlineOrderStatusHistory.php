<?php

declare(strict_types=1);

namespace Modules\OnlineOrders\Models;

use Illuminate\Database\Eloquent\Model;

/** Paso del seguimiento de un pedido. */
class OnlineOrderStatusHistory extends Model
{
    public const SOURCE_WEB = 'web';

    public const SOURCE_SISTEMA = 'sistema';

    protected $fillable = ['online_order_id', 'status', 'note', 'internal', 'source', 'user_id', 'user_name', 'happened_at'];

    protected $casts = [
        'internal' => 'boolean',
        'happened_at' => 'datetime',
    ];
}
