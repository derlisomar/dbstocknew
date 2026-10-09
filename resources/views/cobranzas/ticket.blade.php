<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Cobro #{{ $cobro->cob_id }}</title>
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
    @php($sucursal = $cobro->sesion->caja->sucursal ?? null)
    <div class="text-center">
        <h2 style="margin: 0; font-size: 16px;">MI EMPRESA S.A.</h2>
        @if($sucursal)
            <p style="margin: 2px 0;">{{ $sucursal->suc_direccion }}</p>
            <p style="margin: 2px 0;">TELEFONO: {{ $sucursal->suc_telefono }}</p>
        @endif
    </div>
    <div class="divider"></div>
    <p class="text-center bold" style="margin: 2px 0; font-size: 14px;">RECIBO DE COBRO</p>
    @if($cobro->cob_estado === 'ANULADA')
        <p class="text-center bold" style="margin: 2px 0;">*** COBRO ANULADO ***</p>
    @endif
    <p style="margin: 2px 0;">RECIBO NRO: {{ str_pad($cobro->cob_id, 10, '0', STR_PAD_LEFT) }}</p>
    <p style="margin: 2px 0;">FECHA: {{ \Carbon\Carbon::parse($cobro->cob_fecha)->format('d/m/Y H:i') }}</p>
    <div class="divider"></div>
    <p style="margin: 2px 0;" class="bold">CLIENTE: {{ trim(($cobro->cliente->cli_nombre ?? '').' '.($cobro->cliente->cli_apellido ?? '')) ?: 'N/A' }}</p>
    <p style="margin: 2px 0;">CI. / RUC.: {{ $cobro->cliente->cli_ruc_ci ?? '' }}</p>
    <div class="divider"></div>
    <table>
        <tr><th>CUENTA / VENTA</th><th class="text-right">PAGADO</th></tr>
        @foreach($cobro->detalles as $d)
        <tr>
            <td>Cuenta #{{ $d->cred_id }} (Venta {{ $d->cuenta->vta_id ?? '-' }})</td>
            <td class="text-right">{{ number_format($d->det_monto_pagado, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td colspan="2" style="font-size: 11px;">Saldo actual de la cuenta: Gs. {{ number_format($d->cuenta->cred_saldo_pendiente ?? 0, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>
    <div class="divider"></div>
    <p class="text-right bold" style="font-size: 14px;">TOTAL COBRADO: Gs. {{ number_format($cobro->cob_monto_total, 0, ',', '.') }}</p>
    <p style="margin: 2px 0;">FORMA DE PAGO: {{ $cobro->cob_formapago ?: 'EFECTIVO' }}</p>
    <div class="divider"></div>
    <p style="margin: 2px 0;">COBRADO POR: {{ $cobro->usuario->usu_nombre ?? $cobro->usuario->usu_usuario ?? '' }}</p>
    <div class="text-center" style="margin-top: 10px;">
        <p class="bold">***GRACIAS***</p>
        <p style="margin-top: 5px;">*COMPROBANTE INTERNO - NO VALIDO COMO FACTURA*</p>
    </div>
</body>
</html>
