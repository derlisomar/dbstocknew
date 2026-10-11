@php
    $cfg = config('impresion.matricial');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $factura ? 'Factura' : 'Comprobante' }} matricial #{{ $venta->vta_id }}</title>
    <style>
        /* Texto de 80 columnas: Courier a 12 pt = 10 caracteres por pulgada; 6 líneas por pulgada. */
        @page { size: {{ $cfg['ancho_mm'] }}mm {{ $cfg['alto_mm'] }}mm; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        pre.hoja {
            margin: 0;
            padding: {{ $cfg['margen_sup_mm'] }}mm 0 0 {{ $cfg['margen_izq_mm'] }}mm;
            font-family: 'Courier New', Courier, monospace;
            font-size: 12pt;
            line-height: 12pt;
            white-space: pre;
        }
        .aviso { display: none; }
        @media screen {
            body { background: #e5e7eb; padding: 16px; }
            pre.hoja { background: #fff; width: {{ $cfg['ancho_mm'] }}mm; min-height: {{ $cfg['alto_mm'] }}mm; box-shadow: 0 2px 10px rgba(0,0,0,.25); box-sizing: border-box; }
            .aviso { display: block; font: 13px sans-serif; color: #374151; margin-bottom: 10px; }
        }
    </style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); }">
    <div class="aviso">Vista previa de la hoja para la impresora matricial. Elegí la impresora (por ejemplo EPSON LX-350) y el papel continuo en la ventana de impresión.</div>
<pre class="hoja">{{ implode("\n", $lineas) }}</pre>
</body>
</html>
