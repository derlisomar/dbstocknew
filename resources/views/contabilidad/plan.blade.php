@extends('layouts.admin')

@section('contenido')
@include('contabilidad._estilos')
<div class="ct">
    <div class="ct-head"><div><h2 class="ct-title">Plan de cuentas</h2>
        <p class="ct-sub">Las cuentas marcadas «sistema» las usa el motor de asientos: se pueden renombrar pero no desactivar.</p></div></div>
    @if(session('success'))<div class="ct-alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="ct-alert bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="ct-alert bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @include('contabilidad._menu')

    @can('CONTABILIDAD_GESTIONAR')
    <form method="POST" action="{{ route('contabilidad.cuenta.guardar') }}" class="ct-card">
        @csrf
        <h3>Nueva cuenta</h3>
        <div class="ct-grid">
            <div><label class="ct-label">Código</label><input name="codigo" class="ct-in" maxlength="20" placeholder="5.2.09" value="{{ old('codigo') }}" required></div>
            <div><label class="ct-label">Nombre</label><input name="nombre" class="ct-in" maxlength="120" value="{{ old('nombre') }}" required></div>
            <div><label class="ct-label">Tipo</label><select name="tipo" class="ct-in">@foreach($tipos as $k => $v)<option value="{{ $k }}" @selected(old('tipo') === $k)>{{ $v }}</option>@endforeach</select></div>
            <div><label class="ct-label">¿Recibe asientos?</label><select name="imputable" class="ct-in"><option value="1">Sí (cuenta de movimiento)</option><option value="0">No (título que agrupa)</option></select></div>
        </div>
        <div style="margin-top:12px"><button class="ct-btn" type="submit">Crear cuenta</button></div>
    </form>
    @endcan

    <div class="ct-card ct-tw">
        <table class="ct-table">
            <thead><tr><th>Código</th><th>Cuenta</th><th>Tipo</th><th>Estado</th>@can('CONTABILIDAD_GESTIONAR')<th></th>@endcan</tr></thead>
            <tbody>
            @foreach($cuentas as $c)
                <tr>
                    <td><b>{{ $c->cue_codigo }}</b></td>
                    <td style="padding-left:{{ 10 + substr_count($c->cue_codigo, '.') * 14 }}px">
                        @can('CONTABILIDAD_GESTIONAR')
                        <form method="POST" action="{{ route('contabilidad.cuenta.actualizar', $c->cue_id) }}" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
                            @csrf @method('PUT')
                            <input name="nombre" class="ct-in" style="max-width:340px" value="{{ $c->cue_nombre }}" maxlength="120">
                            <label class="ct-mu"><input type="checkbox" name="activa" value="1" @checked($c->cue_activa)> activa</label>
                            <button class="ct-btn ghost sm" type="submit">Guardar</button>
                        </form>
                        @else
                            {{ $c->cue_nombre }}
                        @endcan
                    </td>
                    <td>{{ $tipos[$c->cue_tipo] ?? $c->cue_tipo }}</td>
                    <td>
                        @if(! $c->cue_imputable)<span class="ct-badge mute">Título</span>@endif
                        @if($c->cue_clave)<span class="ct-badge info">Sistema</span>@endif
                        @if(! $c->cue_activa)<span class="ct-badge bad">Inactiva</span>@endif
                    </td>
                    @can('CONTABILIDAD_GESTIONAR')<td></td>@endcan
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
