<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Ticket #{{ $venta->vta_nro_factura }}</title>
    <!-- Mismos estilos CSS que el modelo 1 -->
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 72mm; margin: 0 auto; padding: 5px; font-size: 12px; line-height: 1.2; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .divider { border-bottom: 1px dashed #000; margin: 5px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; }
        @media print { @page { margin: 0; } body { margin: 0; } }
    </style>
</head>
<body onload="window.print(); window.onafterprint = function(){ window.close(); }">
@if(!empty($copia))
    <div style="text-align:center;font-weight:bold;border:1px solid #000;margin-bottom:5px;padding:2px;">*** COPIA / REIMPRESION ***</div>
@endif
    <div class="text-center">
        <h2 style="margin: 0; font-size: 16px;">MI EMPRESA S.A.</h2>
        <p style="margin: 2px 0;">RUC: 80001234-5</p>
        <p style="margin: 2px 0;">{{ $venta->sucursal->suc_actividad_economica }}</p>
        <p style="margin: 2px 0;">{{ $venta->sucursal->suc_direccion }}</p>
        <p style="margin: 2px 0;">TELEFONO: {{ $venta->sucursal->suc_telefono }}</p>
    </div>
    <div class="divider"></div>
    <p style="margin: 2px 0;" class="bold">TIMBRADO: {{ $venta->vta_timbrado }}</p>
    <p style="margin: 2px 0;">INICIO VIGENCIA: {{ \Carbon\Carbon::parse($venta->sucursal->suc_timbrado_inicio)->format('d/m/Y') }}</p>
    <p style="margin: 2px 0;">VALIDO HASTA: {{ \Carbon\Carbon::parse($venta->sucursal->suc_timbrado_fin)->format('d/m/Y') }}</p>
    <p style="margin: 2px 0;" class="bold">FACTURA NRO: {{ $venta->sucursal->suc_est_punto_exp }}-{{ str_pad($venta->vta_nro_factura, 7, '0', STR_PAD_LEFT) }}</p>
    <div class="divider"></div>
    <p style="margin: 2px 0;" class="bold">CI. / RUC.: {{ $venta->cliente->cli_ruc_ci ?? '44444401-7' }}</p>
    <p style="margin: 2px 0;" class="bold">CLIENTE: {{ $venta->cliente->cli_nombre ?? 'CLIENTE OCASIONAL' }}</p>
    <div class="divider"></div>
    
    <table>
        <tr>
            <th>CANT.</th>
            <th>DESC/MONTO</th>
            <th class="text-right">TOTAL</th>
        </tr>
        @foreach($venta->detalles as $det)
        <tr>
            <td colspan="3" class="bold">{{ $det->producto->pro_codigo }} {{ $det->producto->pro_nombre }}</td>
        </tr>
        <tr>
            <td>{{ $det->det_cantidad }}</td>
            <td>{{ number_format($det->det_preciounitario, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($det->det_subtotal, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>
    
    <div class="divider"></div>
    <p class="text-right bold" style="font-size: 14px;">TOTAL A PAGAR: Gs. {{ number_format($venta->vta_total, 0, ',', '.') }}</p>
    
    <div class="divider"></div>
    <!-- LIQUIDACIÓN DE IVA REQUERIDA POR LEY -->
    <table style="font-size: 11px;">
        <tr>
            <th>SUB TOTAL</th>
            <th class="text-right">LIQUIDACION</th>
            <th class="text-right">IVA</th>
        </tr>
        <tr>
            <td>Exentas:</td>
            <td class="text-right">{{ number_format($venta->vta_total_exenta, 0, ',', '.') }}</td>
            <td class="text-right">0</td>
        </tr>
        <tr>
            <td>5%:</td>
            <td class="text-right">{{ number_format($venta->vta_total_iva5 * 21, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($venta->vta_total_iva5, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td>10%:</td>
            <td class="text-right">{{ number_format($venta->vta_total_iva10 * 11, 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($venta->vta_total_iva10, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="2" class="bold">TOTAL IVA:</td>
            <td class="text-right bold">{{ number_format($venta->vta_total_iva5 + $venta->vta_total_iva10, 0, ',', '.') }}</td>
        </tr>
    </table>
    
    <div class="divider"></div>
    <p style="margin: 2px 0;">ATENDIDO POR: {{ $venta->usuario->usu_nombre }}</p>
    <p style="margin: 2px 0;">F. DE EMISION: {{ \Carbon\Carbon::parse($venta->vta_fecha)->format('d/m/Y h:i A') }}</p>
    
    <div class="text-center" style="margin-top: 10px;">
        <p>VERIFIQUE SU COMPRA Y VUELTO EN CAJA. NO ACEPTAMOS RECLAMOS POSTERIORES</p>
        <p>ORIGINAL: CLIENTE - COMPRADOR</p>
        <p class="bold">***GRACIAS POR SU COMPRA***</p>
    </div>
</body>
</html>