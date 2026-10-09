<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket Interno #{{ $venta->vta_id }}</title>
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
    <p style="margin: 2px 0;">CONDICION: {{ $venta->vta_tipo }}</p>
    <p style="margin: 2px 0;">OPERACION NRO: {{ str_pad($venta->vta_id, 10, '0', STR_PAD_LEFT) }}</p>
    <p style="margin: 2px 0;">AGENCIA: 1 &nbsp;&nbsp;&nbsp; CAJA: {{ $venta->caja->caj_id ?? 1 }}</p>
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
    <p class="text-right bold" style="font-size: 14px;">TOTAL: Gs. {{ number_format($venta->vta_total, 0, ',', '.') }}</p>
    <div class="divider"></div>
    <p style="margin: 2px 0;">ATENDIDO POR: {{ $venta->usuario->usu_nombre }}</p>
    <p style="margin: 2px 0;">F. DE EMISION: {{ \Carbon\Carbon::parse($venta->vta_fecha)->format('d/m/Y h:i A') }}</p>
    <div class="text-center" style="margin-top: 10px;">
        <p class="bold">***GRACIAS POR SU COMPRA***</p>
        <p style="margin-top: 5px;">*NO VALIDO COMO FACTURA*</p>
        <p>*USO INTERNO*</p>
    </div>
</body>
</html>