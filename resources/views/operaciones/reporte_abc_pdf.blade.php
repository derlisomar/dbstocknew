<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Rentabilidad y curva ABC</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 20px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p { margin: 0 0 10px; color: #555; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f0f0f0; }
        .r { text-align: right; }
        .c { text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <h1>Rentabilidad y curva ABC</h1>
    <p>
        @if(request('fecha_inicio') || request('fecha_fin'))
            Periodo: {{ request('fecha_inicio') ?: 'inicio' }} al {{ request('fecha_fin') ?: 'hoy' }}.
        @else
            Todo el historial de ventas.
        @endif
        Total vendido: Gs. {{ number_format($granTotalIngresos, 0, ',', '.') }}. Impreso el {{ now()->format('d/m/Y H:i') }}.
    </p>
    <table>
        <thead>
            <tr>
                <th class="c">Clase</th><th>Producto</th><th class="r">Unidades</th>
                <th class="r">Ingreso</th><th class="r">Costo</th><th class="r">Utilidad</th><th class="c">Margen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($datos as $d)
                <tr>
                    <td class="c">{{ $d->clasificacion_abc }}</td>
                    <td>{{ $d->pro_codigo }} - {{ $d->pro_nombre }}</td>
                    <td class="r">{{ (float) $d->total_vendido }}</td>
                    <td class="r">{{ number_format($d->ingresos_totales, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format($d->costo_total, 0, ',', '.') }}</td>
                    <td class="r">{{ number_format($d->utilidad_bruta, 0, ',', '.') }}</td>
                    <td class="c">{{ $d->sin_costo ? 'Sin costo' : number_format($d->margen_porcentual, 1, ',', '.').'%' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
