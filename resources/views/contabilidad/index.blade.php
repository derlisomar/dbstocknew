@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
@php $fmt = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.'); @endphp
<div class="ct">
    <div class="ct-head">
        <div>
            <h2 class="ct-title">Contabilidad</h2>
            <p class="ct-sub">Cada venta, cobro, compra, pago y cierre de caja genera su asiento solo. Acá controlás los libros, los balances y el IVA.</p>
        </div>
    </div>

    @if(session('success'))<div class="ct-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ct-alert bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ct-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif

    @if(! $preparada)
        <div class="ct-card">
            <h3>Preparar la contabilidad</h3>
            <p class="ct-sub" style="margin-bottom:12px">Se crea un plan de cuentas básico (que tu contador puede ajustar) y se elige desde qué fecha se contabiliza. Las operaciones anteriores a esa fecha no se tocan: se cargan con un asiento de apertura con los saldos iniciales.</p>
            @can('CONTABILIDAD_GESTIONAR')
                <form method="POST" action="{{ route('contabilidad.preparar') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                    @csrf
                    <div><label class="ct-label">Contabilizar desde</label>
                        <input type="date" name="inicio" class="ct-in" value="{{ old('inicio', now()->startOfMonth()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
                    <button class="ct-btn ok" type="submit">Preparar contabilidad</button>
                </form>
            @else
                <p class="ct-mu">Pedile a un administrador que prepare la contabilidad.</p>
            @endcan
        </div>
    @else
        @include('contabilidad._menu')

        @if(count($sync['errores']))
            <div class="ct-alert bad">
                <b>Hay operaciones que no se pudieron contabilizar:</b>
                @foreach(array_slice($sync['errores'], 0, 5) as $e)<div>{{ $e }}</div>@endforeach
                <div class="ct-mu" style="margin-top:6px">Lo más común es una cuenta faltante o desactivada. Revisá el Plan de cuentas y los Mapeos; se reintenta solo.</div>
            </div>
        @endif

        <div class="ct-stats">
            <div class="ct-stat ok"><span class="ct-label">Asientos</span><b>{{ number_format($asientos, 0, ',', '.') }}</b></div>
            <div class="ct-stat {{ $pendientes > 0 ? 'warn' : 'ok' }}"><span class="ct-label">Operaciones pendientes</span><b>{{ $pendientes }}</b></div>
            <div class="ct-stat info"><span class="ct-label">Se contabiliza desde</span><b>{{ $inicio }}</b></div>
            <div class="ct-stat {{ $cerradoHasta ? 'warn' : 'info' }}"><span class="ct-label">Período cerrado hasta</span><b>{{ $cerradoHasta ?? 'Ninguno' }}</b></div>
        </div>

        <div class="ct-quad">
            <div class="ct-card">
                <h3>Saldos principales</h3>
                <table class="ct-table">
                    @foreach($saldos as $s)
                        <tr><td><a href="{{ route('contabilidad.mayor', ['cuenta' => $s['id'], 'desde' => now()->startOfYear()->toDateString()]) }}">{{ $s['nombre'] }}</a></td><td class="ct-num"><b>{{ $fmt($s['saldo']) }}</b></td></tr>
                    @endforeach
                </table>
                @php $contable = $saldos['mercaderias']['saldo'] ?? 0; @endphp
                <p class="ct-hint">Mercaderías según el stock del sistema: <b>{{ $fmt($inventarioSistema) }}</b>
                    @if(abs($inventarioSistema - $contable) >= 1)
                        · diferencia con la contabilidad: <b>{{ $fmt($inventarioSistema - $contable) }}</b>. Si recién empezás, cargá el asiento de apertura con el valor del inventario.
                    @else
                        · coincide con la contabilidad.
                    @endif
                </p>
            </div>

            <div class="ct-card">
                <h3>Resultado del mes</h3>
                <table class="ct-table">
                    <tr><td>Ingresos</td><td class="ct-num">{{ $fmt($resultadoMes['total_ingresos']) }}</td></tr>
                    <tr><td>Costos y gastos</td><td class="ct-num">{{ $fmt($resultadoMes['total_egresos']) }}</td></tr>
                    <tr class="ct-sub"><td>{{ $resultadoMes['resultado'] >= 0 ? 'Ganancia' : 'Pérdida' }}</td><td class="ct-num">{{ $fmt($resultadoMes['resultado']) }}</td></tr>
                </table>
                <p class="ct-hint">Última actualización: {{ $ultimaSync ? \Carbon\Carbon::parse($ultimaSync)->format('d/m/Y H:i') : 'todavía no corrió' }}.</p>
                <form method="POST" action="{{ route('contabilidad.sincronizar') }}" style="margin-top:8px">@csrf
                    <button class="ct-btn ghost sm" type="submit">Actualizar ahora</button></form>
            </div>
        </div>

        @if($puedeGestionar)
        <div class="ct-card">
            <h3>Cierre de período</h3>
            <p class="ct-sub" style="margin-bottom:10px">Al cerrar hasta una fecha, nadie puede cargar asientos manuales en esos días y las operaciones atrasadas se contabilizan en el primer día abierto. Útil al presentar la declaración del mes.</p>
            <form method="POST" action="{{ route('contabilidad.periodo') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                @csrf
                <div><label class="ct-label">Cerrar hasta (inclusive)</label>
                    <input type="date" name="hasta" class="ct-in" max="{{ now()->subDay()->toDateString() }}"></div>
                <button class="ct-btn" type="submit">Cerrar período</button>
            </form>
            @if($cerradoHasta)
                <form method="POST" action="{{ route('contabilidad.periodo') }}" style="margin-top:10px" onsubmit="return confirm('¿Reabrir todos los períodos? Se podrán cargar asientos en fechas pasadas.')">
                    @csrf <input type="hidden" name="reabrir" value="1">
                    <button class="ct-btn ghost sm" type="submit">Reabrir períodos</button>
                </form>
            @endif
        </div>
        @endif

        <div class="ct-card">
            <h3>Cómo se contabiliza</h3>
            <table class="ct-table">
                <tr><td><b>Venta al contado</b></td><td>Caja o banco (según la forma de pago) al debe; ventas e IVA débito al haber.</td></tr>
                <tr><td><b>Costo de lo vendido</b></td><td>Costo de mercaderías vendidas al debe; mercaderías al haber (con el costo guardado en cada venta).</td></tr>
                <tr><td><b>Venta a crédito y cobranza</b></td><td>Deudores al debe en la venta; al cobrar, caja o banco al debe y deudores al haber.</td></tr>
                <tr><td><b>Compra y pago</b></td><td>Mercaderías e IVA crédito al debe, proveedores al haber; el pago baja la deuda contra caja o banco.</td></tr>
                <tr><td><b>Cierre de caja</b></td><td>Faltante o sobrante contra la caja, y diferencia de cambio del efectivo en dólares y reales.</td></tr>
                <tr><td><b>Ajuste de inventario</b></td><td>Faltantes, mermas y conteos: pérdida (o sobrante) contra mercaderías, al costo.</td></tr>
            </table>
        </div>
    @endif
</div>
@endsection
