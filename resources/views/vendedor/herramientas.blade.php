@extends('vendedor.layout')
@section('titulo', 'Herramientas')
@section('contenido')
<h1>Herramientas</h1>
<p class="pv-sub">Tareas de preparación y mantenimiento del sistema de este negocio.</p>

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
