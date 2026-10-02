<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1e293b; margin: 0; }
        .header { border-bottom: 2px solid #3366ff; padding-bottom: 8px; margin-bottom: 12px; }
        h1 { font-size: 16px; margin: 0; color: #1c2f88; }
        .muted { color: #64748b; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #eef4ff; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; color: #1836d6; }
        td { padding: 5px 8px; border-bottom: 1px solid #e6e8f0; }
        tr:nth-child(even) td { background: #f8fafc; }
        .summary { margin-top: 14px; padding: 10px; background: #f1f5f9; border-radius: 6px; }
        .summary strong { color: #1c2f88; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="muted">{{ config('app.name') }} · Generado el {{ $generatedAt }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach($headings as $h)<th>{{ $h }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                @foreach($row as $cell)<td>{{ is_numeric($cell) ? number_format((float) $cell, 2) : $cell }}</td>@endforeach
            </tr>
            @empty
            <tr><td colspan="{{ count($headings) }}" style="text-align:center;padding:20px;color:#94a3b8;">Sin datos en el rango seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if(!empty($summary))
    <div class="summary">
        @foreach($summary as $key => $value)
            <strong>{{ ucfirst(str_replace('_', ' ', $key)) }}:</strong>
            {{ is_numeric($value) ? number_format((float) $value, 2) : $value }}&nbsp;&nbsp;
        @endforeach
    </div>
    @endif
</body>
</html>
