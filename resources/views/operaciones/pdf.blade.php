<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Historial de Ventas</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h2 { text-align: center; margin-bottom: 2px; color: #111; }
        .subtitle { text-align: center; font-size: 11px; color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background-color: #f8fafc; font-size: 11px; text-transform: uppercase; color: #475569; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print();">
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">🖨️ Imprimir / Guardar PDF</button>
    </div>

    <h2>dbstock - Reporte de Historial de Ventas</h2>
    <div class="subtitle">Generado el: {{ date('d/m/Y H:i') }}</div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Tipo / Pago</th>
                <th class="text-center">Ítems</th>
                <th class="text-right">Total (Gs.)</th>
                <th class="text-right">USD Equiv.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ventas as $v)
            <tr>
                <td>#{{ $v->vta_id }}</td>
                <td>{{ \Carbon\Carbon::parse($v->vta_fecha)->format('d/m/Y H:i') }}</td>
                <td>{{ $v->cliente->cli_nombre ?? 'Consumidor Final' }}</td>
                <td>{{ $v->vta_tipo }} / {{ $v->vta_formapago ?? 'N/A' }}</td>
                <td class="text-center">{{ $v->detalles->sum('det_cantidad') }}</td>
                <td class="text-right">Gs. {{ number_format($v->vta_total, 0, ',', '.') }}</td>
                <td class="text-right">$ {{ number_format($v->vta_total / $tasaUsd, 2, '.', ',') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>