@extends('vendedor.layout')
@section('titulo', 'Solicitudes de demo')
@section('contenido')
@php
    $est = ['ACTIVA' => ['b-ok', 'Activa'], 'VENCIDA' => ['b-mu', 'Vencida'], 'FALLIDA' => ['b-bad', 'Correo no enviado'], 'REPETIDA' => ['b-mu', 'Ya tenía acceso']];
    $wa = function ($t, $nombre) {
        $d = preg_replace('/\D+/', '', (string) $t);
        if ($d === '') { return null; }
        if (str_starts_with($d, '0')) { $d = '595'.substr($d, 1); }
        return 'https://wa.me/'.$d.'?text='.rawurlencode("Hola {$nombre}, te escribimos de ".config('landing.empresa').' por tu prueba de dbstock. ¿Pudiste probarlo? Contanos qué te pareció.');
    };
@endphp
<div class="pv-head"><div><h1>Solicitudes de demo</h1><p class="pv-sub">Personas que pidieron probar el sistema desde la página pública. Son posibles clientes: escribiles mientras la demo está activa.</p></div></div>
@if(($conteo['FALLIDA'] ?? 0) > 0)
    <div class="al bad">Hay {{ $conteo['FALLIDA'] }} solicitud(es) a las que NO se les pudo mandar el acceso por correo. Revisá el motivo en la lista, probá el envío en <a href="{{ route('vendedor.herramientas') }}">Herramientas > Probar correo</a> y después tocá «Reintentar envío».</div>
@endif
<div class="pv-stats">
    <div class="stat"><div class="n">{{ $filas->count() }}</div><div class="t">Solicitudes</div></div>
    <div class="stat ac"><div class="n">{{ $conteo['ACTIVA'] ?? 0 }}</div><div class="t">Demos activas</div></div>
    <div class="stat"><div class="n">{{ $conteo['VENCIDA'] ?? 0 }}</div><div class="t">Vencidas</div></div>
</div>
<div class="pv-card">
    @if(! $hay)<div class="vacio">Todavía no se preparó la tabla de demos. Usá «Preparar base de datos» en Herramientas.</div>
    @else
    <div class="tw"><table>
        <thead><tr><th>Fecha</th><th>Persona</th><th>Negocio</th><th>Contacto</th><th>Estado</th><th>Vence</th></tr></thead>
        <tbody>
        @forelse($filas as $f)
            @php [$c, $t] = $est[$f->dem_estado] ?? ['b-mu', $f->dem_estado]; $link = $wa($f->dem_telefono, $f->dem_nombre); @endphp
            <tr><td style="white-space:nowrap">{{ \Carbon\Carbon::parse($f->dem_creada)->format('d/m/Y H:i') }}</td>
                <td><b>{{ $f->dem_nombre }}</b></td><td>{{ $f->dem_negocio }}</td>
                <td><a href="mailto:{{ $f->dem_email }}">{{ $f->dem_email }}</a>@if($f->dem_telefono)<div class="mu">{{ $f->dem_telefono }} @if($link)· <a href="{{ $link }}" target="_blank" rel="noopener">WhatsApp</a>@endif</div>@endif</td>
                <td><span class="badge {{ $c }}">{{ $t }}</span>
                    @if($f->dem_detalle ?? null)<div class="mu" style="max-width:340px;white-space:normal;margin-top:4px;font-size:12px">{{ $f->dem_detalle }}</div>@endif
                    @if($f->dem_estado === 'FALLIDA')<form method="POST" action="{{ route('vendedor.demos.reintentar', $f->dem_id) }}" style="margin-top:6px">@csrf<button class="btn sm">Reintentar envío</button></form>@endif</td>
                <td class="mu">{{ $f->dem_vence ? \Carbon\Carbon::parse($f->dem_vence)->format('d/m/Y') : '-' }}</td></tr>
        @empty<tr><td colspan="6" class="vacio">Todavía no llegó ninguna solicitud.</td></tr>@endforelse
        </tbody></table></div>
    @endif
</div>
@endsection
