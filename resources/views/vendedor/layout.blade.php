<!doctype html>
<html lang="es" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>@yield('titulo', 'Panel del vendedor') - dbstock</title>
<script>
try{var t=localStorage.getItem('pv-tema');if(t!=='dark'&&t!=='light')t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t)}catch(e){}
</script>
<style>
@font-face{font-family:"Geist";src:url("{{ asset('fonts/landing/Geist-Variable.woff2') }}") format("woff2");font-weight:100 900;font-display:swap}
:root{--bg:#f3f7f7;--card:#fff;--bd:#dde7e8;--tx:#0e1a1d;--mu:#5b6f75;--soft:#f3f8f8;--ac:#0a8487;--ac-h:#086f72;--ac-soft:#dff2f2;--ac-tx:#08696c;--ac-ink:#fff;
  --side:#0b1a1e;--side-tx:#a9bfc4;--side-on:#16343a;--side-bd:#16292e;--sh:0 1px 2px rgba(14,43,48,.05),0 8px 24px -12px rgba(14,43,48,.15);--r:14px;color-scheme:light}
:root[data-theme=dark]{--bg:#0a1114;--card:#111c20;--bd:#1f3036;--tx:#e8f1f1;--mu:#93a8ad;--soft:rgba(255,255,255,.035);--ac:#2fc9cc;--ac-h:#52d8da;--ac-soft:#0f2b2e;--ac-tx:#58d7d9;--ac-ink:#03191a;
  --side:#070e11;--side-tx:#8aa3a9;--side-on:#10262b;--side-bd:#13242a;--sh:0 1px 2px rgba(0,0,0,.4),0 8px 24px -12px rgba(0,0,0,.6);color-scheme:dark}
*{box-sizing:border-box}
html{-webkit-text-size-adjust:100%}
body{margin:0;font-family:"Geist",system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:var(--bg);color:var(--tx);font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
a{color:var(--ac-tx)}
.i{width:18px;height:18px;flex:none;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}

/* ---- estructura ---- */
.pv-app{display:grid;grid-template-columns:260px minmax(0,1fr);min-height:100vh}
.pv-side{background:var(--side);color:var(--side-tx);position:sticky;top:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column;padding:18px 14px;border-right:1px solid var(--side-bd)}
.pv-logo{text-decoration:none;display:block;padding:4px 8px 16px}
.pv-logo img{height:46px;width:auto;display:block}
.pv-logo small{display:block;margin-top:6px;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#59767c;font-weight:600}
.pv-grp{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#59767c;font-weight:700;padding:16px 10px 6px}
.pv-side a.pv-lnk{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:10px;color:var(--side-tx);text-decoration:none;font-weight:550;font-size:13.5px;transition:background .15s,color .15s}
.pv-side a.pv-lnk:hover{background:var(--side-on);color:#fff}
.pv-side a.pv-lnk.on{background:var(--ac);color:var(--ac-ink)}
:root[data-theme=dark] .pv-side a.pv-lnk.on{background:var(--side-on);color:var(--ac-tx);box-shadow:inset 3px 0 0 var(--ac)}
.pv-side-pie{margin-top:auto;padding-top:16px;display:flex;gap:8px}
.pv-side-pie button,.pv-side-pie .pv-ic{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:9px;border-radius:10px;border:1px solid var(--side-bd);background:transparent;color:var(--side-tx);font:inherit;font-weight:600;font-size:13px;cursor:pointer}
.pv-side-pie button:hover{background:var(--side-on);color:#fff}
.pv-main{min-width:0}
.pv-bar{display:none}
.pv-wrap{max-width:1120px;margin:0 auto;padding:28px 28px 70px}
.pv-wrap.solo{max-width:430px;padding-top:9vh}

/* ---- encabezados ---- */
.pv-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px}
h1{font-size:25px;line-height:1.2;margin:0 0 4px;letter-spacing:-.02em;font-weight:700}
.pv-sub{color:var(--mu);margin:0 0 22px;max-width:70ch}
.pv-head .pv-sub{margin:0}

/* ---- tarjetas ---- */
.pv-card{background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:20px;margin-bottom:18px;box-shadow:var(--sh)}
.pv-card h2{font-size:15px;margin:0 0 14px;font-weight:650;display:flex;align-items:center;gap:8px}
.pv-grid{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(210px,1fr))}
.pv-2{display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));align-items:start}
.pv-2>.pv-card{margin-bottom:0}
.pv-stats{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));margin-bottom:18px}
.stat{background:var(--card);border:1px solid var(--bd);border-radius:var(--r);padding:16px 18px;box-shadow:var(--sh)}
.stat .n{font-size:26px;font-weight:700;letter-spacing:-.02em;line-height:1.15}
.stat .t{font-size:12px;color:var(--mu);font-weight:600;margin-top:2px;text-transform:uppercase;letter-spacing:.06em}
.stat.ac{background:var(--ac);border-color:var(--ac);color:var(--ac-ink)}.stat.ac .t{color:inherit;opacity:.8}

/* ---- formularios ---- */
label.l{display:block;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--mu);margin-bottom:5px}
.in{width:100%;padding:9px 12px;border-radius:10px;border:1px solid var(--bd);background:var(--card);color:var(--tx);font:inherit;transition:border-color .15s,box-shadow .15s}
textarea.in{resize:vertical;min-height:90px}
.in:focus{outline:none;border-color:var(--ac);box-shadow:0 0 0 3px color-mix(in srgb,var(--ac) 22%,transparent)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9px 16px;border-radius:10px;border:1px solid transparent;font:inherit;font-size:13px;font-weight:650;cursor:pointer;text-decoration:none;background:var(--ac);color:var(--ac-ink);transition:background .15s,transform .1s}
.btn:hover{background:var(--ac-h)}.btn:active{transform:translateY(1px)}
.btn.g{background:transparent;color:var(--tx);border-color:var(--bd)}.btn.g:hover{background:var(--soft)}
.btn.r{background:#dc2626;color:#fff}.btn.r:hover{background:#b91c1c}
.btn.ok{background:#059669;color:#fff}.btn.ok:hover{background:#047857}
.btn.sm{padding:5px 11px;font-size:12px;border-radius:8px}
.acciones{display:flex;gap:8px;flex-wrap:wrap}
.sw{display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer}
.sw input{width:16px;height:16px;accent-color:var(--ac)}

/* ---- tablas ---- */
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;font-size:11px;letter-spacing:.05em;text-transform:uppercase;color:var(--mu);padding:9px 12px;border-bottom:1px solid var(--bd);white-space:nowrap;font-weight:700}
td{padding:11px 12px;border-bottom:1px solid var(--bd);vertical-align:middle}
tbody tr:hover td{background:var(--soft)}
tr:last-child td{border-bottom:0}.tw{overflow-x:auto;margin:0 -6px}.r{text-align:right}

/* ---- etiquetas y avisos ---- */
.badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap}
.b-ok{background:rgba(16,185,129,.15);color:#047857}.b-warn{background:rgba(245,158,11,.18);color:#b45309}.b-bad{background:rgba(239,68,68,.15);color:#b91c1c}.b-mu{background:var(--soft);color:var(--mu);border:1px solid var(--bd)}.b-info{background:var(--ac-soft);color:var(--ac-tx)}
:root[data-theme=dark] .b-ok{color:#34d399}:root[data-theme=dark] .b-warn{color:#fbbf24}:root[data-theme=dark] .b-bad{color:#f87171}
.al{padding:11px 15px;border-radius:12px;font-weight:600;margin-bottom:16px}
.al.ok{background:rgba(16,185,129,.12);color:#047857;border:1px solid rgba(16,185,129,.35)}.al.bad{background:rgba(239,68,68,.1);color:#b91c1c;border:1px solid rgba(239,68,68,.35)}
:root[data-theme=dark] .al.ok{color:#6ee7b7}:root[data-theme=dark] .al.bad{color:#fca5a5}
.mu{color:var(--mu);font-size:12.5px}
.chk{display:flex;gap:12px;align-items:flex-start;padding:11px 0;border-bottom:1px solid var(--bd)}.chk:last-child{border-bottom:0;padding-bottom:0}
.dot{flex:none;width:22px;height:22px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;background:#cbd5d6;margin-top:1px}
.dot.ok{background:#059669}.dot.warn{background:#d97706}.dot.bad{background:#dc2626}
.bar{height:8px;border-radius:99px;background:var(--bd);overflow:hidden}.bar i{display:block;height:100%;background:linear-gradient(90deg,var(--ac),#34d399);border-radius:99px}
pre{background:#0b1220;color:#d1d5db;padding:14px;border-radius:10px;overflow:auto;font-size:12px;white-space:pre-wrap}
.mod{display:flex;gap:10px;align-items:flex-start;padding:11px 12px;border:1px solid var(--bd);border-radius:12px;background:var(--soft);cursor:pointer}
.mod input{margin-top:3px;accent-color:var(--ac)}
.vacio{padding:26px;text-align:center;color:var(--mu)}
.pager{display:flex;justify-content:space-between;margin-top:14px}

/* ---- vista previa de planes ---- */
.pv-prev{display:grid;gap:14px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
.pp{border:1px solid var(--bd);border-radius:14px;padding:18px;background:var(--card);position:relative}
.pp.main{border-color:var(--ac);box-shadow:0 0 0 1px var(--ac)}
.pp .tag{position:absolute;top:-10px;left:50%;transform:translateX(-50%);background:var(--ac);color:var(--ac-ink);font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;padding:2px 10px;border-radius:999px;white-space:nowrap}
.pp h3{margin:0;font-size:16px}.pp .pr{font-size:21px;font-weight:700;margin:8px 0 2px}.pp ul{list-style:none;margin:10px 0 0;padding:0;display:grid;gap:5px;font-size:12.5px}
.pp li.off{text-decoration:line-through;color:var(--mu)}

@media (max-width:900px){
  .pv-app{grid-template-columns:1fr}
  .pv-side{position:fixed;inset:0 auto 0 0;width:280px;z-index:30;transform:translateX(-100%);transition:transform .2s}
  body.menu .pv-side{transform:none;box-shadow:0 0 0 100vmax rgba(0,0,0,.5)}
  .pv-bar{display:flex;align-items:center;gap:12px;padding:10px 16px;background:var(--side);color:#fff;position:sticky;top:0;z-index:20}
  .pv-bar button{background:none;border:0;color:#fff;cursor:pointer;display:flex;padding:4px}
  .pv-bar b{font-size:14px}
  .pv-wrap{padding:20px 16px 60px}
  .pv-2{grid-template-columns:1fr}
}
@media print{.pv-side,.pv-bar{display:none}.pv-app{display:block}body{background:#fff}}
</style>
</head>
<body>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="p-home" viewBox="0 0 24 24"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></symbol>
<symbol id="p-store" viewBox="0 0 24 24"><path d="M4 21V8l8-5 8 5v13M9 21v-6h6v6M4 21h16"/></symbol>
<symbol id="p-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/></symbol>
<symbol id="p-pin" viewBox="0 0 24 24"><path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></symbol>
<symbol id="p-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.6-3.4 3.3-5.5 6.5-5.5s5.9 2.1 6.5 5.5"/><path d="M16 4.7a3.5 3.5 0 0 1 0 6.6M18.5 14.8c1.6.8 2.7 2.4 3 5.2"/></symbol>
<symbol id="p-tag" viewBox="0 0 24 24"><path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.2"/></symbol>
<symbol id="p-card" viewBox="0 0 24 24"><rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6 15h4"/></symbol>
<symbol id="p-play" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2.5"/><path d="m10 9 5 3-5 3z"/></symbol>
<symbol id="p-db" viewBox="0 0 24 24"><ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v13c0 1.7 3.6 3 8 3s8-1.3 8-3v-13M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></symbol>
<symbol id="p-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></symbol>
<symbol id="p-tools" viewBox="0 0 24 24"><path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/></symbol>
<symbol id="p-out" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></symbol>
<symbol id="p-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></symbol>
<symbol id="p-moon" viewBox="0 0 24 24"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></symbol>
<symbol id="p-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
<symbol id="p-print" viewBox="0 0 24 24"><path d="M7 9V3h10v6M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M7 14h10v7H7z"/></symbol>
</defs></svg>
@php
    $menu = [
        'Principal' => [['vendedor.resumen', 'Resumen', 'home']],
        'Negocio' => [['vendedor.negocio', 'Datos del negocio', 'store'], ['vendedor.modulos', 'Edición y módulos', 'layers'], ['vendedor.estructura', 'Sucursales y cajas', 'pin'], ['vendedor.usuarios', 'Usuarios', 'users']],
        'Comercial' => [['vendedor.planes', 'Planes y precios', 'tag'], ['vendedor.licencia', 'Licencia y pagos', 'card'], ['vendedor.demos', 'Solicitudes de demo', 'play']],
        'Sistema' => [['vendedor.respaldos', 'Respaldos', 'db'], ['vendedor.historial', 'Historial', 'clock'], ['vendedor.herramientas', 'Herramientas', 'tools']],
    ];
    $logeado = (bool) session('vendedor_hasta');
@endphp
@if($logeado)
<div class="pv-app">
    <aside class="pv-side" id="pv-side">
        <a class="pv-logo" href="{{ route('vendedor.resumen') }}">
            <img src="{{ asset('img/landing/logo-oscuro.png') }}" alt="Dobi Soluciones Informáticas">
            <small>Panel del vendedor</small>
        </a>
        @foreach($menu as $grupo => $items)
            <div class="pv-grp">{{ $grupo }}</div>
            @foreach($items as [$ruta, $texto, $ico])
                <a href="{{ route($ruta) }}" class="pv-lnk {{ request()->routeIs($ruta) ? 'on' : '' }}"><svg class="i"><use href="#p-{{ $ico }}"/></svg>{{ $texto }}</a>
            @endforeach
        @endforeach
        <div class="pv-side-pie">
            <button type="button" id="pv-tema" aria-label="Cambiar entre modo claro y oscuro"><svg class="i"><use href="#p-moon"/></svg>Tema</button>
            <form method="POST" action="{{ route('vendedor.salir') }}" style="flex:1;display:flex">@csrf<button style="flex:1"><svg class="i"><use href="#p-out"/></svg>Salir</button></form>
        </div>
    </aside>
    <div class="pv-main">
        <div class="pv-bar"><button type="button" id="pv-menu" aria-label="Abrir el menú"><svg class="i"><use href="#p-menu"/></svg></button><b>Panel del vendedor</b></div>
@endif
<div class="pv-wrap {{ $logeado ? '' : 'solo' }}">
    @if(session('success'))<div class="al ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="al bad">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="al bad">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @yield('contenido')
</div>
@if($logeado)
    </div>
</div>
@endif
<script>
(function(){
    var r=document.documentElement,b=document.getElementById('pv-tema');
    if(b)b.addEventListener('click',function(){var t=r.getAttribute('data-theme')==='dark'?'light':'dark';r.setAttribute('data-theme',t);try{localStorage.setItem('pv-tema',t)}catch(e){}});
    var m=document.getElementById('pv-menu');
    if(m){m.addEventListener('click',function(){document.body.classList.add('menu')});
        document.addEventListener('click',function(e){if(document.body.classList.contains('menu')&&!e.target.closest('#pv-side')&&!e.target.closest('#pv-menu'))document.body.classList.remove('menu')})}
})();
</script>
</body>
</html>
