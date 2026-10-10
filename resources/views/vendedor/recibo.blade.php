<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Recibo {{ str_pad((string) $pago->lpa_id, 6, '0', STR_PAD_LEFT) }}</title>
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#111;margin:0;background:#f3f4f6}
.hoja{max-width:720px;margin:24px auto;background:#fff;padding:40px;border:1px solid #e5e7eb;border-radius:10px}
.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid #0a8487;padding-bottom:18px}
h1{margin:0;font-size:22px}.num{font-size:13px;color:#555;margin-top:4px}
table{width:100%;border-collapse:collapse;margin-top:22px}td{padding:10px 0;border-bottom:1px solid #eee;vertical-align:top}td:first-child{color:#666;width:34%}
.monto{font-size:26px;font-weight:700;margin:24px 0 6px}
.anulado{color:#b91c1c;font-weight:700;border:2px solid #b91c1c;display:inline-block;padding:2px 10px;border-radius:6px;transform:rotate(-4deg)}
.pie{margin-top:36px;color:#666;font-size:12px}
.acc{max-width:720px;margin:16px auto 0;display:flex;gap:8px}
.acc button,.acc a{padding:9px 16px;border-radius:8px;border:1px solid #d1d5db;background:#fff;font:inherit;cursor:pointer;text-decoration:none;color:#111}
.acc button{background:#0a8487;color:#fff;border-color:#0a8487}
@media print{body{background:#fff}.hoja{border:0;margin:0;padding:0}.acc{display:none}}
</style></head><body>
<div class="acc"><button onclick="window.print()">Imprimir</button><a href="{{ route('vendedor.licencia') }}">Volver</a></div>
<div class="hoja">
    <div class="top">
        <div><h1>Recibo de pago</h1><div class="num">N.º {{ str_pad((string) $pago->lpa_id, 6, '0', STR_PAD_LEFT) }}</div></div>
        <div style="text-align:right"><b>{{ $emisor }}</b><div class="num">Fecha: {{ $pago->lpa_fecha->format('d/m/Y') }}</div></div>
    </div>
    @if($pago->lpa_estado !== 'ACTIVO')<p><span class="anulado">ANULADO</span> {{ $pago->lpa_motivo_anulacion }}</p>@endif
    <div class="monto">Gs. {{ number_format((float) $pago->lpa_monto, 0, ',', '.') }}</div>
    <table>
        <tr><td>Recibimos de</td><td><b>{{ $negocio }}</b>@if($ruc)<br>RUC {{ $ruc }}@endif</td></tr>
        <tr><td>Concepto</td><td>Servicio dbstock{{ $pago->plan ? ', plan '.$pago->plan->plan_nombre : '' }}@if($pago->lpa_nota)<br>{{ $pago->lpa_nota }}@endif</td></tr>
        <tr><td>Período cubierto</td><td>{{ $pago->lpa_hasta ? ($pago->lpa_desde ? $pago->lpa_desde->format('d/m/Y').' al ' : 'Hasta el ').$pago->lpa_hasta->format('d/m/Y') : 'Pago único (licencia permanente)' }}</td></tr>
        <tr><td>Forma de pago</td><td>{{ $pago->lpa_forma ?: '-' }}@if($pago->lpa_referencia) · {{ $pago->lpa_referencia }}@endif</td></tr>
    </table>
    <div class="pie">Comprobante de pago del servicio. No reemplaza a la factura.</div>
</div>
</body></html>
