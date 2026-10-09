<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('titulo', 'Panel del vendedor') - dbstock</title>
<style>
:root{--bg:#f3f4f6;--card:#fff;--bd:#e5e7eb;--tx:#111827;--mu:#6b7280;--soft:#f9fafb;--ac:#2563eb;--nav:#111827;--navtx:#e5e7eb}
@media (prefers-color-scheme:dark){:root{--bg:#0f1623;--card:#1c2434;--bd:#2e3a47;--tx:#f3f4f6;--mu:#9ca3af;--soft:rgba(255,255,255,.04);--nav:#0a0f1a}}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:var(--bg);color:var(--tx);font-size:14px}
a{color:var(--ac)}
.pv-top{background:var(--nav);color:var(--navtx);padding:0 16px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;position:sticky;top:0;z-index:10}
.pv-brand{font-weight:800;padding:14px 12px 14px 0;margin-right:8px;color:#fff}
.pv-top a{color:var(--navtx);text-decoration:none;padding:14px 10px;font-size:13px;font-weight:600;border-bottom:3px solid transparent}
.pv-top a:hover{color:#fff}.pv-top a.on{color:#fff;border-bottom-color:#3b82f6}
.pv-top form{margin-left:auto}
.pv-wrap{max-width:1080px;margin:0 auto;padding:20px 16px 60px}
h1{font-size:22px;margin:0 0 4px}.pv-sub{color:var(--mu);margin:0 0 18px}
.pv-card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:16px}
.pv-card h2{font-size:15px;margin:0 0 12px}
.pv-grid{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
label.l{display:block;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--mu);margin-bottom:4px}
.in{width:100%;padding:8px 10px;border-radius:8px;border:1px solid var(--bd);background:var(--card);color:var(--tx);font-size:14px}
.in:focus{outline:2px solid #3b82f6;outline-offset:-1px}
.btn{display:inline-block;padding:8px 14px;border-radius:8px;border:1px solid transparent;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none;background:var(--ac);color:#fff}
.btn:hover{filter:brightness(.92)}
.btn.g{background:transparent;color:var(--tx);border-color:var(--bd)}.btn.r{background:#dc2626}.btn.ok{background:#059669}.btn.sm{padding:4px 10px;font-size:12px}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:10px;letter-spacing:.06em;text-transform:uppercase;color:var(--mu);padding:8px 10px;border-bottom:1px solid var(--bd);white-space:nowrap}
td{padding:9px 10px;border-bottom:1px solid var(--bd);vertical-align:middle}
tr:last-child td{border-bottom:0}.tw{overflow-x:auto}.r{text-align:right}
.badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700}
.b-ok{background:rgba(16,185,129,.15);color:#059669}.b-warn{background:rgba(245,158,11,.18);color:#b45309}.b-bad{background:rgba(239,68,68,.15);color:#dc2626}.b-mu{background:var(--soft);color:var(--mu)}.b-info{background:rgba(59,130,246,.15);color:#2563eb}
@media (prefers-color-scheme:dark){.b-ok{color:#34d399}.b-warn{color:#fbbf24}.b-bad{color:#f87171}.b-info{color:#93c5fd}}
.al{padding:10px 14px;border-radius:10px;font-weight:600;margin-bottom:14px}
.al.ok{background:rgba(16,185,129,.12);color:#047857;border:1px solid rgba(16,185,129,.35)}.al.bad{background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.35)}
@media (prefers-color-scheme:dark){.al.ok{color:#6ee7b7}.al.bad{color:#fca5a5}}
.mu{color:var(--mu);font-size:12px}
.chk{display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--bd)}.chk:last-child{border-bottom:0}
.dot{flex:none;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;background:#d1d5db}
.dot.ok{background:#059669}
.bar{height:8px;border-radius:99px;background:var(--bd);overflow:hidden}.bar i{display:block;height:100%;background:#059669}
pre{background:#0b1220;color:#d1d5db;padding:12px;border-radius:8px;overflow:auto;font-size:12px;white-space:pre-wrap}
.mod{display:flex;gap:10px;align-items:flex-start;padding:10px;border:1px solid var(--bd);border-radius:10px;background:var(--soft)}
.mod input{margin-top:3px}
</style>
</head>
<body>
@php $nav = ['vendedor.resumen' => 'Resumen', 'vendedor.negocio' => 'Negocio', 'vendedor.modulos' => 'Edición y módulos', 'vendedor.estructura' => 'Sucursales y cajas', 'vendedor.usuarios' => 'Usuarios', 'vendedor.planes' => 'Planes', 'vendedor.licencia' => 'Licencia y pagos', 'vendedor.herramientas' => 'Herramientas']; @endphp
@if(session('vendedor_hasta'))
<div class="pv-top">
    <span class="pv-brand">Panel del vendedor</span>
    @foreach($nav as $ruta => $texto)<a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta) ? 'on' : '' }}">{{ $texto }}</a>@endforeach
    <form method="POST" action="{{ route('vendedor.salir') }}">@csrf<button class="btn g sm" style="color:#e5e7eb;border-color:#374151">Salir</button></form>
</div>
@endif
<div class="pv-wrap">
    @if(session('success'))<div class="al ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="al bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="al bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('contenido')
</div>
</body>
</html>
