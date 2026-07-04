<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;

/** Serie y correlativo de un tipo de comprobante por sucursal. */
class DocumentSeries extends Model
{
    protected $table = 'document_series';

    protected $fillable = ['branch_id', 'doc_type', 'series', 'current_number', 'is_active'];

    protected $casts = [
        'current_number' => 'integer',
        'is_active' => 'boolean',
    ];
}
