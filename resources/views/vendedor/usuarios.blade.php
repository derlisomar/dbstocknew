@extends('vendedor.layout')
@section('titulo', 'Usuarios')
@section('contenido')
<h1>Usuarios del negocio</h1>
<p class="pv-sub">Creá el administrador inicial del negocio. Después él arma sus propios usuarios y roles desde el sistema.</p>

<form method="POST" action="{{ route('vendedor.usuarios.administrador') }}" class="pv-card">
    @csrf
    <h2>Nuevo administrador</h2>
    <div class="pv-grid">
        <div><label class="l">Nombre *</label><input class="in" name="usu_nombre" value="{{ old('usu_nombre') }}" required maxlength="100"></div>
        <div><label class="l">Apellido *</label><input class="in" name="usu_apellido" value="{{ old('usu_apellido') }}" required maxlength="100"></div>
        <div><label class="l">Cédula *</label><input class="in" name="usu_cedula" value="{{ old('usu_cedula') }}" required maxlength="20"></div>
        <div><label class="l">Usuario *</label><input class="in" name="usu_usuario" value="{{ old('usu_usuario') }}" required maxlength="50" autocomplete="off"></div>
        <div><label class="l">Correo *</label><input class="in" type="email" name="usu_email" value="{{ old('usu_email') }}" required maxlength="150"></div>
        <div><label class="l">Clave (mínimo 8) *</label><input class="in" type="text" name="usu_password" required minlength="8" maxlength="100" autocomplete="off"></div>
    </div>
    <div style="margin-top:12px"><button class="btn ok">Crear administrador</button></div>
</form>

<div class="pv-card">
    <h2>Usuarios existentes</h2>
    <div class="tw"><table>
        <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Restablecer clave</th></tr></thead>
        <tbody>
        @forelse($usuarios as $u)
            <tr><td><b>{{ $u->usu_usuario }}</b></td><td>{{ $u->usu_nombre }} {{ $u->usu_apellido }}</td><td>{{ $u->rol_nombre ?? '—' }}</td>
                <td><span class="badge {{ $u->usu_activo ? 'b-ok' : 'b-mu' }}">{{ $u->usu_activo ? 'Activo' : 'Inactivo' }}</span></td>
                <td><form method="POST" action="{{ route('vendedor.usuarios.clave', $u->usu_id) }}" style="display:flex;gap:6px">@csrf
                    <input class="in" style="width:160px" name="clave" minlength="8" maxlength="100" placeholder="Nueva clave" required autocomplete="off">
                    <button class="btn g sm" onclick="return confirm('¿Cambiar la clave de {{ $u->usu_usuario }}?')">Cambiar</button></form></td></tr>
        @empty<tr><td colspan="5" class="mu">Todavía no hay usuarios.</td></tr>@endforelse
        </tbody></table></div>
</div>
@endsection
