@extends('vendedor.layout')
@section('titulo', 'Edición y módulos')
@section('contenido')
<h1>Edición y módulos</h1>
<p class="pv-sub">Elegí una edición como punto de partida y, si hace falta, prendé o apagá módulos sueltos para este negocio.</p>

<div class="pv-card">
    <h2>Edición actual: {{ config('modulos.ediciones')[$edicion] }}</h2>
    <div class="pv-grid">
        @foreach(config('modulos.ediciones') as $clave => $nombre)
            <form method="POST" action="{{ route('vendedor.modulos.edicion') }}" class="mod" style="display:block">
                @csrf<input type="hidden" name="edicion" value="{{ $clave }}">
                <b>{{ $nombre }}</b> @if($edicion === $clave)<span class="badge b-ok">Actual</span>@endif
                <div class="mu" style="margin:6px 0 10px">
                    @if($clave === 'COMPLETA') Todos los módulos activados.
                    @else Venta rápida, caja, cobranzas, presupuestos, inventario y reportes básicos. Sin compras, promociones, reportes avanzados, depósitos, auditoría ni varias monedas.@endif
                </div>
                <button class="btn {{ $edicion === $clave ? 'g' : '' }} sm" onclick="return confirm('Esto vuelve los módulos a los valores por defecto de la edición. ¿Continuar?')">Aplicar {{ $nombre }}</button>
            </form>
        @endforeach
    </div>
</div>

<form method="POST" action="{{ route('vendedor.modulos.guardar') }}" class="pv-card">
    @csrf
    <h2>Módulos de este negocio</h2>
    <div class="mu" style="margin-bottom:10px">Siempre incluido: {{ implode(' · ', config('modulos.nucleo')) }}.</div>
    <div class="pv-grid">
        @foreach(config('modulos.catalogo') as $clave => $m)
            <label class="mod"><input type="checkbox" name="modulos[]" value="{{ $clave }}" @checked(in_array($clave, $activos, true))>
                <span><b>{{ $m['nombre'] }}</b><br><span class="mu">{{ $m['descripcion'] }}</span></span></label>
        @endforeach
    </div>
    <h2 style="margin-top:18px">Límites (0 = sin límite)</h2>
    <div class="pv-grid">
        <div><label class="l">Sucursales activas</label><input class="in" type="number" min="0" name="limite_sucursales" value="{{ $limites['sucursales'] }}"></div>
        <div><label class="l">Cajas activas</label><input class="in" type="number" min="0" name="limite_cajas" value="{{ $limites['cajas'] }}"></div>
        <div><label class="l">Usuarios activos</label><input class="in" type="number" min="0" name="limite_usuarios" value="{{ $limites['usuarios'] }}"></div>
    </div>
    <div style="margin-top:16px"><button class="btn ok">Guardar módulos y límites</button></div>
    <p class="mu">Los límites solo frenan al negocio: desde este panel siempre podés crear lo que necesites.</p>
</form>
@endsection
