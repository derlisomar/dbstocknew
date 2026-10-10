@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Mapeo de cuentas</h2>
        <p class="ct-sub">Indicá a qué cuenta contable va el dinero de cada forma de pago y cada categoría de producto. Lo que dejes en «Predeterminada» usa la cuenta base.</p></div></div>
    @if(session('success'))<div class="ct-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ct-alert bad">{{ session('error') }}</div>@endif
    @include('contabilidad._menu')

    <form method="POST" action="{{ route('contabilidad.mapeos.guardar') }}">
        @csrf
        <div class="ct-card ct-tw">
            <h3>Formas de cobro y de pago</h3>
            <table class="ct-table">
                <thead><tr><th>Forma</th><th>Cuenta contable</th></tr></thead>
                <tbody>
                @foreach($pagos as $clave => $p)
                    <tr>
                        <td>{{ $p[0] }}</td>
                        <td><select name="pago[{{ $clave }}]" class="ct-in" style="max-width:420px">
                            <option value="">Predeterminada</option>
                            @foreach($cuentas as $c)
                                <option value="{{ $c->cue_id }}" @selected(($map['PAGO|'.$clave] ?? null) === (int) $c->cue_id)>{{ $c->cue_codigo }} · {{ $c->cue_nombre }}</option>
                            @endforeach
                        </select></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="ct-hint">Ej.: si tenés dos bancos, creá una cuenta por banco en el Plan de cuentas y asignala a «Transferencia bancaria» o a las tarjetas.</p>
        </div>

        <div class="ct-card ct-tw">
            <h3>Categorías de productos</h3>
            @if($categorias->isEmpty())
                <p class="ct-mu">Todavía no hay categorías.</p>
            @else
            <table class="ct-table">
                <thead><tr><th>Categoría</th><th>Ingreso por ventas</th><th>Inventario</th><th>Costo de lo vendido</th></tr></thead>
                <tbody>
                @foreach($categorias as $cat)
                    <tr>
                        <td><b>{{ $cat->cat_nombre }}</b></td>
                        @foreach(['ingreso' => 'CAT_INGRESO', 'inventario' => 'CAT_INVENTARIO', 'costo' => 'CAT_COSTO'] as $campo => $tipo)
                            <td><select name="cat_{{ $campo }}[{{ $cat->cat_id }}]" class="ct-in">
                                <option value="">Predeterminada</option>
                                @foreach($cuentas as $c)
                                    <option value="{{ $c->cue_id }}" @selected(($map[$tipo.'|'.$cat->cat_id] ?? null) === (int) $c->cue_id)>{{ $c->cue_codigo }} · {{ $c->cue_nombre }}</option>
                                @endforeach
                            </select></td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="ct-hint">Ej.: la categoría «Servicios» puede ir a «Ingresos por servicios» y no tener inventario ni costo.</p>
            @endif
        </div>
        <button class="ct-btn ok" type="submit">Guardar asignaciones</button>
    </form>
</div>
@endsection
