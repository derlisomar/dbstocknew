@extends('vendedor.layout')
@section('titulo', 'Herramientas')
@section('contenido')
<div class="pv-head"><div><h1>Herramientas</h1>
<p class="pv-sub">Tareas de preparación y mantenimiento del sistema de este negocio.</p></div></div>

<form method="POST" action="{{ route('vendedor.herramientas.instalar') }}" class="pv-card">
    @csrf
    <h2>Instalar o preparar un sistema nuevo</h2>
    <p class="mu" style="margin-top:-6px">Hace todo de una vez: actualiza la base, carga los permisos, crea el administrador (si no hay), la sucursal, el depósito y la caja, y deja lista la contabilidad. Es seguro repetirlo.</p>
    @if($hayAdmin)
        <div class="al ok">Ya hay un administrador activo: no hace falta crear otro.</div>
    @else
        <div class="pv-grid">
            <div><label class="l">Usuario del administrador *</label><input class="in" name="usuario" value="{{ old('usuario', 'admin') }}" required maxlength="50" autocomplete="off"></div>
            <div><label class="l">Nombre *</label><input class="in" name="nombre" value="{{ old('nombre', 'Administrador') }}" required maxlength="100"></div>
            <div><label class="l">Correo *</label><input class="in" type="email" name="email" value="{{ old('email') }}" required maxlength="150"></div>
            <div><label class="l">Contraseña * (mínimo 10)</label><input class="in" type="password" name="clave" required minlength="10" autocomplete="new-password"></div>
        </div>
    @endif
    <label class="sw" style="margin:14px 0"><input type="checkbox" name="demo" value="1"> Cargar productos y clientes de ejemplo (inventados, para una demo)</label>
    <button class="btn ok" onclick="return confirm('Se van a aplicar las actualizaciones de la base y crear lo que falte. ¿Continuar?')">Instalar / preparar</button>
</form>

<div class="pv-card">
    <h2>Preparar base de datos</h2>
    @if($faltan)<div class="al bad">Faltan tablas: {{ implode(', ', $faltan) }}.</div>@else<p class="mu">Todas las tablas del sistema están creadas.</p>@endif
    <p class="mu">Crea las tablas que falten, aplica las actualizaciones pendientes y sincroniza el catálogo de permisos. Es seguro repetirlo; no borra datos.</p>
    <form method="POST" action="{{ route('vendedor.herramientas.preparar') }}">@csrf<button class="btn ok" onclick="return confirm('Se van a aplicar las actualizaciones pendientes de la base de datos. ¿Continuar? (Hacé una copia de seguridad antes en producción.)')">Preparar base de datos</button></form>
</div>

<div class="pv-card">
    <h2>Verificación del sistema</h2>
    <p class="mu">Revisa tablas, índices, permisos y que el stock coincida con su historial.</p>
    <form method="POST" action="{{ route('vendedor.herramientas.verificar') }}">@csrf<button class="btn">Verificar sistema</button></form>
</div>

<div class="pv-card">
    <h2>Limpiar caché</h2>
    <p class="mu">Úsalo después de cambiar el archivo .env o de copiar archivos nuevos.</p>
    <form method="POST" action="{{ route('vendedor.herramientas.cache') }}">@csrf<button class="btn g">Limpiar caché</button></form>
</div>

@if($salida)<div class="pv-card"><h2>Resultado</h2><pre>{{ $salida }}</pre></div>@endif
@endsection
