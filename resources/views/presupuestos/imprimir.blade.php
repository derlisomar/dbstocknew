<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $p->numero }} - Presupuesto</title>
<style>
  *{box-sizing:border-box}
  body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;background:#f3f4f6}
  .hoja{max-width:800px;margin:20px auto;background:#fff;padding:36px 40px;box-shadow:0 2px 12px rgba(0,0,0,.12)}
  .barra{max-width:800px;margin:16px auto 0;display:flex;gap:8px;justify-content:flex-end}
  .barra button,.barra a{padding:8px 14px;border-radius:8px;border:1px solid #d1d5db;background:#2563eb;color:#fff;font-weight:700;font-size:13px;cursor:pointer;text-decoration:none}
  .barra a{background:#fff;color:#111}
  h1{margin:0;font-size:24px} .mu{color:#6b7280;font-size:12px}
  .cab{display:flex;justify-content:space-between;gap:16px;border-bottom:2px solid #111;padding-bottom:14px;margin-bottom:18px}
  .num{text-align:right}.num b{font-size:18px}
  .datos{display:flex;justify-content:space-between;gap:16px;margin-bottom:18px;font-size:13px}
  table{width:100%;border-collapse:collapse;font-size:13px}
  th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;border-bottom:2px solid #111;padding:8px 6px}
  td{padding:8px 6px;border-bottom:1px solid #e5e7eb}
  .r{text-align:right}
  .total{margin-top:14px;text-align:right;font-size:20px;font-weight:800}
  .venc{margin-top:18px;padding:10px 12px;border:1px solid #111;border-radius:6px;font-size:13px}
  .cond{margin-top:12px;font-size:12px;white-space:pre-line}
  .pie{margin-top:36px;font-size:11px;color:#6b7280;text-align:center}
  @media print{body{background:#fff}.hoja{box-shadow:none;margin:0;max-width:none;padding:0}.barra{display:none}}
</style>
</head>
<body>
@php
    $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');
    $suc = \App\Models\Sucursal::find($p->suc_id ?: 1) ?? \App\Models\Sucursal::first();
@endphp
<div class="barra">
    <a href="{{ route('presupuestos.show', $p->pre_id) }}">Volver</a>
    <button onclick="window.print()">Imprimir / Guardar como PDF</button>
</div>
<div class="hoja">
    <div class="cab">
        <div>
            <h1>{{ \App\Services\ConfiguracionService::nombreNegocio() }}</h1>
            @if(\App\Services\ConfiguracionService::get('negocio_ruc'))<div class="mu">RUC: {{ \App\Services\ConfiguracionService::get('negocio_ruc') }}</div>@endif
            @if($suc)<div class="mu">{{ $suc->suc_nombre ?? '' }}{{ ! empty($suc->suc_direccion) ? ' · '.$suc->suc_direccion : '' }}{{ ! empty($suc->suc_telefono) ? ' · Tel. '.$suc->suc_telefono : '' }}</div>@endif
        </div>
        <div class="num"><div class="mu">PRESUPUESTO</div><b>{{ $p->numero }}</b>
            <div class="mu">{{ $p->pre_fecha->setTimezone(config('presupuestos.zona'))->format('d/m/Y') }}</div></div>
    </div>

    <div class="datos">
        <div><div class="mu">Cliente</div><b>{{ $p->nombre_cliente }}</b>
            @if($p->cliente && $p->cliente->cli_ruc_ci)<div>RUC/CI: {{ $p->cliente->cli_ruc_ci }}</div>@endif
            @if($p->cliente && $p->cliente->cli_telefono)<div>Tel.: {{ $p->cliente->cli_telefono }}</div>@endif</div>
        <div style="text-align:right"><div class="mu">Atendido por</div><b>{{ $p->usuario->usu_usuario ?? '' }}</b></div>
    </div>

    <table>
        <thead><tr><th>Producto</th><th class="r">Cantidad</th><th class="r">Precio unit.</th><th class="r">Subtotal</th></tr></thead>
        <tbody>
        @foreach($p->detalles as $d)
            <tr>
                <td>{{ $d->producto->pro_nombre ?? 'Producto' }}</td>
                <td class="r">{{ rtrim(rtrim(number_format((float) $d->dpr_cantidad, 2, ',', '.'), '0'), ',') }}</td>
                <td class="r">{{ $fmt($d->dpr_precio) }}</td>
                <td class="r">{{ $fmt($d->dpr_subtotal) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="total">Total: {{ $fmt($p->pre_total) }}</div>
    <div class="mu" style="text-align:right">IVA incluido en los precios</div>

    <div class="venc">Presupuesto válido por {{ $p->pre_validez_dias }} días, hasta el <b>{{ $p->pre_fecha_vencimiento->format('d/m/Y') }}</b>.
        Los precios y la disponibilidad pueden cambiar pasada esa fecha.</div>
    @if($p->pre_condiciones)<div class="cond"><b>Condiciones:</b> {{ $p->pre_condiciones }}</div>@endif
    <div class="pie">Este documento es un presupuesto y no constituye factura ni reserva de mercadería.</div>
</div>
</body>
</html>
