@php
    $docLabels = ['ticket' => 'TICKET DE VENTA', 'boleta' => 'BOLETA DE VENTA', 'factura' => 'FACTURA', 'cotizacion' => 'COTIZACIÓN'];
    $sym = $company->currency_symbol ?? 'S/';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #000; margin: 0; padding: 4px 8px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .muted { color: #444; }
        hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .totals td { padding: 1px 0; }
        h1 { font-size: 13px; margin: 2px 0; }
    </style>
</head>
<body>
    <div class="center">
        <h1>{{ $company->business_name ?? 'Voroz' }}</h1>
        @if($company?->ruc)<div>RUC: {{ $company->ruc }}</div>@endif
        @if($company?->address)<div class="muted">{{ $company->address }}</div>@endif
        @if($company?->phone)<div class="muted">Tel: {{ $company->phone }}</div>@endif
    </div>

    <hr>
    <div class="center bold">{{ $docLabels[$sale->doc_type] ?? 'COMPROBANTE' }}</div>
    <div class="center">{{ $sale->full_number }}</div>
    <hr>

    <div>Fecha: {{ optional($sale->sold_at)->format('d/m/Y H:i') }}</div>
    <div>Cajero: {{ $sale->user?->name }}</div>
    <div>Cliente: {{ $sale->customer?->name ?? 'Público general' }}</div>
    @if($sale->customer?->doc_number)<div>Doc: {{ $sale->customer->doc_number }}</div>@endif

    <hr>
    <table>
        <tr class="bold">
            <td>Cant</td><td>Descripción</td><td class="right">Importe</td>
        </tr>
        @foreach($sale->items as $item)
        <tr>
            <td>{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
            <td>{{ $item->description }}<br><span class="muted">{{ $sym }} {{ number_format($item->price, 2) }} c/u</span></td>
            <td class="right">{{ $sym }} {{ number_format($item->subtotal, 2) }}</td>
        </tr>
        @endforeach
    </table>

    <hr>
    <table class="totals">
        <tr><td>Op. Gravada</td><td class="right">{{ $sym }} {{ number_format($sale->subtotal, 2) }}</td></tr>
        <tr><td>IGV ({{ number_format($sale->tax_percent, 0) }}%)</td><td class="right">{{ $sym }} {{ number_format($sale->tax, 2) }}</td></tr>
        @if($sale->discount > 0)
        <tr><td>Descuento</td><td class="right">- {{ $sym }} {{ number_format($sale->discount, 2) }}</td></tr>
        @endif
        <tr class="bold"><td>TOTAL</td><td class="right">{{ $sym }} {{ number_format($sale->total, 2) }}</td></tr>
    </table>

    @if($sale->payments->count())
    <hr>
    <table>
        @foreach($sale->payments as $p)
        <tr><td>{{ ucfirst($p->method) }}</td><td class="right">{{ $sym }} {{ number_format($p->amount, 2) }}</td></tr>
        @endforeach
        @if($sale->change > 0)
        <tr><td>Vuelto</td><td class="right">{{ $sym }} {{ number_format($sale->change, 2) }}</td></tr>
        @endif
    </table>
    @endif

    <hr>
    <div class="center muted">¡Gracias por su compra!</div>
    <div class="center muted">Generado por Voroz ERP</div>
</body>
</html>
