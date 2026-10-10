@extends('vendedor.layout')
@section('titulo', 'Respaldos')
@section('contenido')
<div class="pv-head">
    <div><h1>Respaldos</h1>
    <p class="pv-sub">Copias de seguridad de la base de datos. Se hacen solas todos los días a las {{ $hora }} y se conservan {{ $dias }} días (siempre quedan las últimas 3).</p></div>
    @if($pgsql)<form method="POST" action="{{ route('vendedor.respaldos.crear') }}">@csrf<button class="btn">Crear respaldo ahora</button></form>@endif
</div>
@unless($pgsql)<div class="al bad">El respaldo desde el panel solo está preparado para PostgreSQL.</div>@endunless
<div class="pv-card">
    <div class="tw"><table>
        <thead><tr><th>Archivo</th><th>Fecha</th><th class="r">Tamaño</th><th></th></tr></thead>
        <tbody>
        @forelse($lista as $b)
            <tr><td><b>{{ $b['nombre'] }}</b></td>
                <td>{{ \Carbon\Carbon::createFromTimestamp($b['fecha'], config('vendedor.zona'))->format('d/m/Y H:i') }}</td>
                <td class="r">{{ number_format($b['bytes'] / 1048576, 2, ',', '.') }} MB</td>
                <td class="r"><a class="btn g sm" href="{{ route('vendedor.respaldos.descargar', $b['nombre']) }}">Descargar</a></td></tr>
        @empty<tr><td colspan="4" class="vacio">Todavía no hay respaldos.</td></tr>@endforelse
        </tbody></table></div>
</div>
<div class="pv-card">
    <h2>Cómo restaurar un respaldo</h2>
    <p class="mu">Por seguridad la restauración no se hace desde el panel: reemplazaría todos los datos actuales. Descargá el archivo y restauralo en una base vacía con <code>pg_restore --no-owner -d NOMBRE_BASE archivo.dump</code>. En cPanel se puede hacer desde phpPgAdmin o pidiéndoselo al soporte del hosting.</p>
</div>
@endsection
