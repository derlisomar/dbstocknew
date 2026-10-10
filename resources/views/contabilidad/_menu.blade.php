@php
    $tabs = [
        ['contabilidad.index', 'Panel', 'contabilidad.index', 'CONTABILIDAD_VER'],
        ['contabilidad.diario', 'Libro diario', 'contabilidad.diario', 'CONTABILIDAD_VER'],
        ['contabilidad.mayor', 'Libro mayor', 'contabilidad.mayor', 'CONTABILIDAD_VER'],
        ['contabilidad.sumas', 'Sumas y saldos', 'contabilidad.sumas', 'CONTABILIDAD_VER'],
        ['contabilidad.resultados', 'Resultados', 'contabilidad.resultados', 'CONTABILIDAD_VER'],
        ['contabilidad.balance', 'Balance general', 'contabilidad.balance', 'CONTABILIDAD_VER'],
        ['contabilidad.iva', 'IVA ventas', 'contabilidad.iva', 'CONTABILIDAD_VER', ['libro' => 'ventas']],
        ['contabilidad.iva', 'IVA compras', 'contabilidad.iva', 'CONTABILIDAD_VER', ['libro' => 'compras']],
        ['contabilidad.plan', 'Plan de cuentas', 'contabilidad.plan', 'CONTABILIDAD_VER'],
        ['contabilidad.mapeos', 'Mapeos', 'contabilidad.mapeos', 'CONTABILIDAD_GESTIONAR'],
        ['contabilidad.asiento.nuevo', 'Asiento manual', 'contabilidad.asiento.nuevo', 'CONTABILIDAD_GESTIONAR'],
    ];
@endphp
<nav class="ct-tabs">
    @foreach($tabs as $t)
        @can($t[3])
            @php
                $params = $t[4] ?? [];
                $activo = request()->routeIs($t[2]) && (! isset($params['libro']) || request()->route('libro') === $params['libro']);
            @endphp
            <a href="{{ route($t[0], $params) }}" class="{{ $activo ? 'on' : '' }}">{{ $t[1] }}</a>
        @endcan
    @endforeach
</nav>
