@php
    $v = fn (string $ruta) => asset($ruta).'?v='.@filemtime(public_path($ruta));
    $dias = (int) config('landing.dias_demo');
    $empresa = config('landing.empresa');
    $wa = config('landing.whatsapp');
    $correo = config('landing.correo_contacto');
    $demoActiva = config('landing.demo_activa');
    $descripcion = 'Dobi Soluciones Informáticas: sistemas a medida, venta de sistemas con equipo informático, páginas web y apps para iOS y Android. Probá dbstock, el sistema de ventas, stock y cobranzas, gratis.';
    $waTexto = rawurlencode('Hola, quiero consultar por los servicios de Dobi Soluciones Informáticas.');
    $waLink = $wa ? "https://wa.me/{$wa}?text={$waTexto}" : null;
    $waVisible = $wa && strlen($wa) === 12 && str_starts_with($wa, '595') ? '+595 '.substr($wa, 3, 3).' '.substr($wa, 6, 3).' '.substr($wa, 9, 3) : ($wa ? '+'.$wa : '');
@endphp
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dobi Soluciones Informáticas | Sistemas, webs y apps a medida | dbstock</title>
    <meta name="description" content="{{ $descripcion }}">
    <meta name="theme-color" content="#0a1114" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f8f8" media="(prefers-color-scheme: light)">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Dobi Soluciones Informáticas | Sistemas, webs y apps a medida">
    <meta property="og:description" content="{{ $descripcion }}">
    <meta property="og:image" content="{{ asset('img/landing/captura-panel.webp') }}">
    <link rel="icon" type="image/png" href="{{ asset('img/landing/favicon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/landing/apple-touch-icon.png') }}">
    <link rel="preload" href="{{ asset('fonts/landing/Geist-Variable.woff2') }}" as="font" type="font/woff2" crossorigin>
    <script>
        try {
            var t = localStorage.getItem('dbstock-tema');
            if (t !== 'dark' && t !== 'light') t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch (e) {}
    </script>
    <link rel="stylesheet" href="{{ $v('css/landing.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
@include('landing._iconos')

<header class="top">
    <div class="wrap">
        <a class="brand" href="#inicio" aria-label="Dobi Soluciones Informáticas, ir al inicio">
            <img class="logo-light" src="{{ asset('img/landing/logo-claro.png') }}" alt="Dobi Soluciones Informáticas" width="108" height="54">
            <img class="logo-dark" src="{{ asset('img/landing/logo-oscuro.png') }}" alt="" width="108" height="54">
        </a>
        <nav class="nav" id="nav" aria-label="Principal">
            <a href="#servicios">Servicios</a>
            <a href="#funciones">dbstock</a>
            <a href="#capturas">Así se ve</a>
            <a href="#planes">{{ ($planes ?? collect())->isNotEmpty() ? 'Planes' : 'Ediciones' }}</a>
            <a href="#contacto">Contacto</a>
            <a class="x" href="{{ \App\Support\Dominios::urlApp('/login') }}">Ingresar al sistema</a>
        </nav>
        <div class="top-actions">
            <a class="btn btn-ghost hide-sm" href="{{ \App\Support\Dominios::urlApp('/login') }}">Ingresar</a>
            @if ($waLink)<a class="btn btn-primary hide-sm" href="{{ $waLink }}" rel="noopener" target="_blank"><svg class="ic"><use href="#i-whatsapp-logo"/></svg>WhatsApp</a>@endif
            <button class="icon-btn" id="tema" type="button" aria-label="Cambiar a modo oscuro">
                <svg class="ic theme-moon"><use href="#i-moon"/></svg>
                <svg class="ic theme-sun"><use href="#i-sun"/></svg>
            </button>
            <button class="icon-btn burger" id="menu" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="nav">
                <svg class="ic"><use href="#i-list"/></svg>
            </button>
        </div>
    </div>
</header>

<main id="inicio">
    <section class="hero">
        <div class="wrap">
            <div>
                <h1 class="rv">Sistemas, páginas web y apps <em>a medida</em> para tu negocio.</h1>
                <p class="lead rv" style="--d:1">En {{ $empresa }} desarrollamos software a tu medida y te lo entregamos listo para trabajar, con el equipo informático incluido si lo necesitás. Y si querés empezar hoy, probá dbstock: ventas, stock y cobranzas en un solo lugar.</p>
                <div class="cta-row rv" style="--d:2">
                    @if ($waLink)<a class="btn btn-primary" href="{{ $waLink }}" rel="noopener" target="_blank"><svg class="ic"><use href="#i-whatsapp-logo"/></svg>Hablar por WhatsApp</a>@endif
                    <a class="btn btn-ghost" href="#demo">Probar dbstock gratis</a>
                </div>
                <div class="mini-facts rv" style="--d:3">
                    <span><svg class="ic"><use href="#i-globe"/></svg>Se usa desde cualquier parte del mundo</span>
                    <span><svg class="ic"><use href="#i-headset"/></svg>Atención en español</span>
                    <span><svg class="ic"><use href="#i-sun"/></svg>Modo claro y oscuro</span>
                </div>
            </div>
            <div class="shots rv" style="--d:2">
                <div class="frame main">
                    <img src="{{ asset('img/landing/captura-panel.webp') }}" width="1600" height="875" alt="dbstock: panel con ventas del día, deuda de clientes y evolución de ingresos" fetchpriority="high">
                </div>
                <div class="frame float">
                    <img src="{{ asset('img/landing/captura-venta.webp') }}" width="1600" height="872" alt="Pantalla del punto de venta de dbstock" loading="lazy">
                </div>
            </div>
        </div>
    </section>

    <section class="sec" id="servicios">
        <div class="wrap">
            <div class="sec-head rv">
                <h2>Todo lo que tu negocio necesita en software, en un solo lugar.</h2>
                <p>Desarrollamos a tu medida o te damos un sistema que ya funciona. Vos elegís por dónde empezar.</p>
            </div>
            <div class="serv">
                <article class="s-card s-db rv">
                    <div class="badge"><svg class="ic"><use href="#i-cash-register"/></svg></div>
                    <h3>dbstock, nuestro sistema listo para usar</h3>
                    <p>Punto de venta, stock, cuentas a cobrar, compras y reportes en un solo sistema web. Empezás rápido, sin instalar nada, y lo probás gratis antes de decidir.</p>
                    <div class="s-act"><a class="btn btn-primary" href="#demo">Probar la demo <svg class="ic ic-go"><use href="#i-arrow-right"/></svg></a><a class="btn btn-ghost" href="#funciones">Ver qué incluye</a></div>
                </article>
                <article class="s-card rv" style="--d:1">
                    <div class="badge"><svg class="ic"><use href="#i-code"/></svg></div>
                    <h3>Sistemas a medida</h3>
                    <p>Software pensado para cómo trabaja tu empresa. Lo diseñamos con vos, lo ponemos en marcha y lo hacemos crecer junto con tu negocio.</p>
                </article>
                <article class="s-card rv" style="--d:2">
                    <div class="badge"><svg class="ic"><use href="#i-desktop-tower"/></svg></div>
                    <h3>Sistema con equipo informático</h3>
                    <p>Te entregamos el sistema instalado junto con el equipo para usarlo. Llegás al local y empezás a trabajar.</p>
                </article>
                <article class="s-card rv" style="--d:1">
                    <div class="badge"><svg class="ic"><use href="#i-browser"/></svg></div>
                    <h3>Páginas web a medida</h3>
                    <p>Sitios con diseño propio, rápidos y pensados para verse bien en el celular, hechos a la medida de tu marca.</p>
                </article>
                <article class="s-card rv" style="--d:2">
                    <div class="badge"><svg class="ic"><use href="#i-device-mobile"/></svg></div>
                    <h3>Apps para iOS y Android</h3>
                    <p>Aplicaciones móviles a medida para tus clientes o para tu equipo, en iPhone y en Android.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="sec alt" id="funciones">
        <div class="wrap">
            <div class="sec-head rv">
                <h2>dbstock: lo que necesitás para manejar tu negocio.</h2>
                <p>Vendé más rápido, sabé siempre cuánto stock te queda y quién te debe. Cada módulo se conecta con los demás, así no cargás nada dos veces.</p>
            </div>
            <div class="bento">
                <article class="tile t-pdv rv">
                    <div class="txt">
                        <div class="badge"><svg class="ic"><use href="#i-cash-register"/></svg></div>
                        <h3>Punto de venta rápido</h3>
                        <p>Escaneá el código o buscá por nombre, elegí el cliente y cobrá. Contado o a crédito, con precios mayoristas y promociones automáticos, y el ticket listo para imprimir.</p>
                        <div class="money" aria-label="Monedas disponibles"><span>Gs.</span><span>USD</span><span>BRL</span></div>
                    </div>
                    <div class="peek"><img src="{{ asset('img/landing/captura-venta.webp') }}" width="1600" height="872" alt="" loading="lazy"></div>
                </article>
                <article class="tile t-cob rv" style="--d:1">
                    <div class="badge"><svg class="ic"><use href="#i-hand-coins"/></svg></div>
                    <h3>Cuentas a cobrar bajo control</h3>
                    <p>Sabé quién te debe, cuánto y desde cuándo. Registrá cobros parciales y mirá la deuda vencida apenas entrás al panel.</p>
                </article>
                <article class="tile t-inv rv">
                    <div class="badge"><svg class="ic"><use href="#i-package"/></svg></div>
                    <h3>Stock siempre al día</h3>
                    <p>Cada venta, compra y ajuste queda en el historial de movimientos. Te avisa qué productos están por debajo del mínimo para que repongas a tiempo.</p>
                </article>
                <article class="tile t-com rv" style="--d:1">
                    <div class="badge"><svg class="ic"><use href="#i-truck"/></svg></div>
                    <h3>Compras y proveedores</h3>
                    <p>Registrá lo que comprás, conocé el costo real de cada producto y controlá lo que le debés a cada proveedor, con sus pagos y vencimientos.</p>
                </article>
                <article class="tile t-pre rv">
                    <div class="badge"><svg class="ic"><use href="#i-file-text"/></svg></div>
                    <h3>Presupuestos</h3>
                    <p>Con fecha de validez. El stock se descuenta recién cuando el cliente acepta y lo pasás a venta.</p>
                </article>
                <article class="tile t-rep rv" style="--d:1">
                    <div class="badge"><svg class="ic"><use href="#i-chart-line-up"/></svg></div>
                    <h3>Reportes para decidir</h3>
                    <p>Rentabilidad por producto, curva ABC y ventas por período. Todo se exporta a Excel y PDF.</p>
                </article>
                <article class="tile t-suc rv" style="--d:2">
                    <div class="badge"><svg class="ic"><use href="#i-shield-check"/></svg></div>
                    <h3>Sucursales y permisos</h3>
                    <p>Varias sucursales y un usuario para cada persona, con los permisos justos. Queda registrado quién hizo cada cosa.</p>
                </article>
                <article class="tile t-con rv">
                    <span class="tag-ed">Solo en la edición completa</span>
                    <div class="badge"><svg class="ic"><use href="#i-seal-check"/></svg></div>
                    <h3>Contabilidad integrada</h3>
                    <p>Cada venta, cobro, compra y cierre de caja genera su asiento contable solo, sin cargar nada dos veces. Libro diario y mayor, balances, resultado del mes y diferencia de cambio en dólares y reales. Elegís a qué cuenta va cada forma de pago y cada categoría, cargás ajustes por faltantes de inventario o de caja, y sacás el libro de IVA de ventas y compras para tu declaración.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="sec pais" id="pais">
        <div class="wrap pais-grid">
            <div class="pais-txt rv">
                <h2>Hecho en Paraguay, para usarlo desde cualquier parte del mundo.</h2>
                <p>dbstock corre en la web, así que tu negocio queda a mano desde Asunción, desde Ciudad del Este o desde cualquier país con internet. Y está pensado para el comercio paraguayo: guaraníes, RUC, IVA del 10 % y del 5 % y facturas con timbrado.</p>
                <ul class="pais-lista">
                    <li><svg class="ic"><use href="#i-coins"/></svg><span><b>Guaraníes y dólares.</b> Cobrás en la moneda que te paguen.</span></li>
                    <li><svg class="ic"><use href="#i-globe"/></svg><span><b>Desde donde estés.</b> Mirá tus ventas y tu stock sin estar en el local.</span></li>
                    <li><svg class="ic"><use href="#i-storefront"/></svg><span><b>Varias sucursales.</b> Cada local con su stock y su caja.</span></li>
                    <li><svg class="ic"><use href="#i-devices"/></svg><span><b>Cualquier dispositivo.</b> Computadora, tablet o celular.</span></li>
                </ul>
                <a class="btn btn-primary" href="#demo">Probalo gratis <svg class="ic ic-go"><use href="#i-arrow-right"/></svg></a>
            </div>
            <div class="mapa rv" style="--d:1">
                @include('landing._mapa')
                <div class="mapa-badge"><svg class="ic"><use href="#i-globe"/></svg>Conectado desde cualquier parte del mundo</div>
            </div>
        </div>
    </section>

    <section class="sec alt" id="capturas">
        <div class="wrap">
            <div class="sec-head rv">
                <h2>Así se ve por dentro.</h2>
                <p>Una interfaz limpia, pensada para usarse todo el día, con modo oscuro para los turnos largos.</p>
            </div>
            <div class="rv">
                <div class="tabs" role="tablist" aria-label="Pantallas del sistema">
                    <button class="tab" role="tab" id="t1" aria-selected="true" aria-controls="p1" data-note="Las ventas del día, la deuda de tus clientes, el stock bajo y la evolución de tus ingresos, apenas entrás.">Panel</button>
                    <button class="tab" role="tab" id="t2" aria-selected="false" aria-controls="p2" data-note="Una pantalla limpia para vender: elegís el cliente, la moneda, la condición y el método de pago, y cobrás.">Punto de venta</button>
                    <button class="tab" role="tab" id="t3" aria-selected="false" aria-controls="p3" data-note="Filtrá por cliente, fecha y tipo de venta. Exportá a Excel o PDF y volvé a sacar el ticket cuando haga falta.">Historial de ventas</button>
                </div>
                <div class="stage">
                    <div class="frame">
                        <img class="panel-img on" id="p1" role="tabpanel" aria-labelledby="t1" src="{{ asset('img/landing/captura-panel.webp') }}" width="1600" height="875" alt="Panel principal de dbstock" loading="lazy">
                        <img class="panel-img" id="p2" role="tabpanel" aria-labelledby="t2" src="{{ asset('img/landing/captura-venta.webp') }}" width="1600" height="872" alt="Punto de venta de dbstock" loading="lazy">
                        <img class="panel-img" id="p3" role="tabpanel" aria-labelledby="t3" src="{{ asset('img/landing/captura-historial.webp') }}" width="1600" height="865" alt="Historial de ventas de dbstock con filtros" loading="lazy">
                    </div>
                    <p class="stage-note" id="stage-note">Las ventas del día, la deuda de tus clientes, el stock bajo y la evolución de tus ingresos, apenas entrás.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="sec" id="como">
        <div class="wrap">
            <div class="sec-head rv">
                <h2>Probalo hoy, sin compromiso.</h2>
                <p>En menos de un minuto tenés tu usuario para entrar al sistema y ver cómo funciona con tus propios ojos.</p>
            </div>
            <div class="steps">
                <div class="step rv">
                    <svg class="ic"><use href="#i-paper-plane-tilt"/></svg>
                    <h3>Pedí tu demo</h3>
                    <p>Completás tu nombre, el de tu negocio y tu correo. Nada más.</p>
                </div>
                <div class="step rv" style="--d:1">
                    <svg class="ic"><use href="#i-envelope-simple"/></svg>
                    <h3>Recibí tu usuario</h3>
                    <p>Te llega un correo con tu usuario y tu clave para entrar.</p>
                </div>
                <div class="step rv" style="--d:2">
                    <svg class="ic"><use href="#i-cash-register"/></svg>
                    <h3>Abrí caja y vendé</h3>
                    <p>Hacé ventas, cargá productos y mirá los reportes con tranquilidad.</p>
                </div>
            </div>
        </div>
    </section>

    @if (($planes ?? collect())->isNotEmpty())
    <section class="sec alt" id="planes">
        <div class="wrap">
            <div class="sec-head rv">
                <h2>{{ $planesTitulo }}</h2>
                <p>{{ $planesTexto }}</p>
                <ul class="pl-chips">
                    <li><svg class="ic"><use href="#i-calendar-check"/></svg>{{ $dias }} días para probar</li>
                    <li><svg class="ic"><use href="#i-seal-check"/></svg>Sin tarjeta</li>
                    <li><svg class="ic"><use href="#i-check"/></svg>Sin contratos largos</li>
                </ul>
            </div>
            @php
                // Los planes con precio van en tarjetas; los "a consultar" (sin precio) van en una franja aparte.
                $conPrecio = $planes->filter(fn ($p) => (float) $p->plan_precio > 0)->values();
                $aMedida = $planes->filter(fn ($p) => (float) $p->plan_precio <= 0)->values();
            @endphp
            @if ($conPrecio->isNotEmpty())
            <div class="pl-grid pl-n{{ min($conPrecio->count(), 4) }}">
                @foreach ($conPrecio as $i => $pl)
                    @php
                        $boton = $pl->plan_boton ?: 'Probar este plan';
                        $enlace = '#demo';
                    @endphp
                    <article class="pl rv {{ $pl->plan_destacado ? 'pl-main' : '' }}" style="--d:{{ $i }}">
                        @if ($pl->plan_etiqueta)<span class="pl-tag">{{ $pl->plan_etiqueta }}</span>@endif
                        <h3>{{ $pl->plan_nombre }}</h3>
                        @if ($pl->plan_descripcion)<p class="pl-for">{{ $pl->plan_descripcion }}</p>@endif
                        <div class="pl-precio">
                            <span class="pl-monto">{{ \App\Services\PlanesPublicos::gs($pl->plan_precio) }}</span><span class="pl-per">{{ $pl->periodoTexto() }}</span>
                        </div>
                        <dl class="pl-lim">
                            <div><dt>Sucursales</dt><dd>{{ $pl->plan_max_sucursales > 0 ? $pl->plan_max_sucursales : 'Ilimitadas' }}</dd></div>
                            <div><dt>Cajas</dt><dd>{{ $pl->plan_max_cajas > 0 ? $pl->plan_max_cajas : 'Ilimitados' }}</dd></div>
                            <div><dt>Usuarios</dt><dd>{{ $pl->plan_max_usuarios > 0 ? $pl->plan_max_usuarios : 'Ilimitados' }}</dd></div>
                        </dl>
                        <ul class="pl-lista">
                            @foreach ($pl->caracteristicas() as $c)
                                <li class="{{ $c['incluye'] ? '' : 'off' }}"><svg class="ic"><use href="#{{ $c['incluye'] ? 'i-check' : 'i-x' }}"/></svg>{{ $c['texto'] }}</li>
                            @endforeach
                        </ul>
                        <a class="btn {{ $pl->plan_destacado ? 'btn-primary' : 'btn-ghost' }}" href="{{ $enlace }}" @if($enlace !== '#demo') target="_blank" rel="noopener" @endif>{{ $boton }}</a>
                    </article>
                @endforeach
            </div>
            @endif

            @foreach ($aMedida as $pl)
                @php
                    $enlaceMed = $waLink ? 'https://wa.me/'.$wa.'?text='.rawurlencode('Hola, quiero consultar por el plan '.$pl->plan_nombre.' de dbstock.') : '#demo';
                @endphp
                <div class="pl-med rv">
                    <div class="pl-med-t">
                        <h3>{{ $pl->plan_nombre }}</h3>
                        @if ($pl->plan_descripcion)<p>{{ $pl->plan_descripcion }}</p>@endif
                        <span class="pl-med-p">A consultar</span>
                    </div>
                    <ul class="pl-med-l">
                        @foreach ($pl->caracteristicas() as $c)
                            <li class="{{ $c['incluye'] ? '' : 'off' }}"><svg class="ic"><use href="#{{ $c['incluye'] ? 'i-check' : 'i-x' }}"/></svg>{{ $c['texto'] }}</li>
                        @endforeach
                    </ul>
                    <a class="btn btn-ghost" href="{{ $enlaceMed }}" @if($enlaceMed !== '#demo') target="_blank" rel="noopener" @endif>{{ $pl->plan_boton ?: 'Consultar' }}</a>
                </div>
            @endforeach

            @if ($adicionales->isNotEmpty())
                <div class="pl-flex rv">
                    <div class="pl-flex-t">
                        <h3>Capacidad flexible</h3>
                        <p>¿Tu negocio creció? Sumá lo que necesites sin cambiar de plan.</p>
                    </div>
                    <ul>
                        @foreach ($adicionales as $ad)
                            <li><span>{{ $ad->ada_nombre }}</span><b>{{ \App\Services\PlanesPublicos::gs($ad->ada_precio) }}<small>/{{ $ad->ada_periodo }}</small></b></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <p class="pl-nota rv">Los precios son en guaraníes y pueden cambiar. Consultanos para una cotización a tu medida.</p>
        </div>
    </section>
    @else
    <section class="sec alt" id="planes">
        <div class="wrap">
            <div class="sec-head rv">
                <h2>Dos ediciones, según el tamaño de tu negocio.</h2>
                <p>Empezá con lo básico para vender rápido y cobrar, y sumá más módulos cuando tu negocio los necesite.</p>
            </div>
            <div class="eds">
                <article class="ed rv">
                    <h3>Básica</h3>
                    <p class="for">Para el comercio que quiere vender rápido y cobrar a crédito sin vueltas.</p>
                    <ul>
                        <li><svg class="ic"><use href="#i-check"/></svg>Punto de venta, contado y crédito</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Cajas con apertura y cierre</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Clientes, productos y cobranzas</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Presupuestos con fecha de validez</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Inventario y ajustes de stock</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Reportes básicos de ventas</li>
                        <li class="off"><svg class="ic"><use href="#i-x"/></svg>Compras y proveedores</li>
                        <li class="off"><svg class="ic"><use href="#i-x"/></svg>Ventas en dólares y reales</li>
                        <li class="off"><svg class="ic"><use href="#i-x"/></svg>Contabilidad integrada</li>
                    </ul>
                    <a class="btn btn-ghost" href="#demo">Probar la demo</a>
                </article>
                <article class="ed main rv" style="--d:1">
                    <span class="tag">Más completa</span>
                    <h3>Completa</h3>
                    <p class="for">Para negocios con compras, más de una sucursal y control fino de cada movimiento.</p>
                    <ul>
                        <li><svg class="ic"><use href="#i-check"/></svg>Todo lo de la edición Básica</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Compras, proveedores y cuentas a pagar</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Promociones y descuentos con fechas</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Reportes de rentabilidad y curva ABC</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Varias sucursales y depósitos</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Ventas en guaraníes, dólares y reales</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Contabilidad: asientos automáticos, balances y libro IVA</li>
                        <li><svg class="ic"><use href="#i-check"/></svg>Registro de auditoría de cada acción</li>
                    </ul>
                    <a class="btn btn-primary" href="#demo">Probar la demo <svg class="ic ic-go"><use href="#i-arrow-right"/></svg></a>
                </article>
            </div>
        </div>
    </section>

    @endif

    <section class="sec" id="demo">
        <div class="wrap demo">
            <div class="rv">
                <h2>Pedí tu demo gratis.</h2>
                <p class="lead">Completá los datos y te mandamos por correo el usuario y la clave para entrar al sistema y probarlo.</p>
                <ul class="demo-points">
                    <li><svg class="ic"><use href="#i-lock-key"/></svg><span><strong>Usuario propio</strong>Entrás con tu usuario y una clave generada solo para vos.</span></li>
                    <li><svg class="ic"><use href="#i-calendar-check"/></svg><span><strong>{{ $dias }} días para probar</strong>Es un ambiente de prueba. No cargues datos reales de tu negocio.</span></li>
                    <li><svg class="ic"><use href="#i-seal-check"/></svg><span><strong>Sin tarjeta ni instalación</strong>Todo corre desde el navegador.</span></li>
                </ul>
            </div>

            <div class="form rv" id="caja-form" style="--d:1">
                @if ($demoActiva)
                    <form id="form-demo" action="{{ route('demo.solicitar') }}" method="post" novalidate>
                        @csrf
                        <div class="fields">
                            <h3>Quiero probar dbstock</h3>
                            <p class="sub">Te lleva menos de un minuto.</p>
                            <div class="alert error" id="demo-error" role="alert"><svg class="ic"><use href="#i-warning-circle"/></svg><span></span></div>
                            <div class="field">
                                <label for="nombre">Tu nombre</label>
                                <input id="nombre" name="nombre" type="text" autocomplete="name" maxlength="100" placeholder="Ej. Marta Benítez" required>
                                <span class="err" aria-live="polite"></span>
                            </div>
                            <div class="field">
                                <label for="negocio">Nombre de tu negocio</label>
                                <input id="negocio" name="negocio" type="text" autocomplete="organization" maxlength="150" placeholder="Ej. Ferretería San Roque" required>
                                <span class="err" aria-live="polite"></span>
                            </div>
                            <div class="grid2">
                                <div class="field">
                                    <label for="email">Correo</label>
                                    <input id="email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="150" placeholder="vos@tunegocio.com" required>
                                    <span class="err" aria-live="polite"></span>
                                </div>
                                <div class="field">
                                    <label for="telefono">Teléfono <small>(opcional)</small></label>
                                    <input id="telefono" name="telefono" type="tel" autocomplete="tel" inputmode="tel" maxlength="40" placeholder="0981 000 000">
                                    <span class="err" aria-live="polite"></span>
                                </div>
                            </div>
                            <div class="hp" aria-hidden="true"><label>No completar este campo<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
                            <button class="btn btn-primary" type="submit"><span class="spin" aria-hidden="true"></span><span class="lbl-go">Quiero mi demo</span><svg class="ic ic-go"><use href="#i-arrow-right"/></svg></button>
                            <p class="fine">Usamos tus datos solo para enviarte el acceso y para contactarte si lo necesitás.</p>
                        </div>
                        <div class="done" id="demo-ok" aria-live="polite">
                            <div class="badge"><svg class="ic"><use href="#i-envelope-simple"/></svg></div>
                            <h3>Revisá tu correo</h3>
                            <p>Si el correo <strong data-email></strong> es válido, en unos instantes te llega tu usuario y tu clave.</p>
                            <p>Si no lo ves, mirá en la carpeta de correo no deseado.</p>
                            <a class="btn btn-primary" href="{{ \App\Support\Dominios::urlApp('/login') }}">Ir al sistema <svg class="ic ic-go"><use href="#i-arrow-right"/></svg></a>
                        </div>
                    </form>
                @else
                    <h3>Las demos están pausadas</h3>
                    <p class="sub">Por ahora no estamos entregando accesos nuevos. Escribinos y te damos uno.</p>
                    @if ($wa)
                        <a class="btn btn-primary" href="https://wa.me/{{ $wa }}" rel="noopener">Escribir por WhatsApp</a>
                    @elseif ($correo)
                        <a class="btn btn-primary" href="mailto:{{ $correo }}">Escribir por correo</a>
                    @endif
                @endif
            </div>
        </div>
    </section>

    <section class="sec alt" id="preguntas">
        <div class="wrap">
            <div class="sec-head rv"><h2>Preguntas frecuentes.</h2></div>
            <div class="faq rv">
                <details>
                    <summary>¿Hacen sistemas a medida?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Sí. Desarrollamos sistemas a la medida de tu empresa, además de páginas web y aplicaciones para iOS y Android. Contanos qué necesitás por WhatsApp o por correo y te respondemos con una propuesta.</p>
                </details>
                <details>
                    <summary>¿Venden también el equipo informático?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Sí. Podés llevarte el sistema ya instalado junto con el equipo para usarlo, así empezás a trabajar apenas llegás a tu local.</p>
                </details>
                <details>
                    <summary>¿Puedo usarlo desde otro país?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Sí. dbstock funciona desde el navegador, así que lo podés usar desde cualquier lugar del mundo con conexión a internet.</p>
                </details>
                <details>
                    <summary>¿Tengo que instalar algo?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">No. dbstock funciona desde el navegador de tu computadora, tablet o celular. Entrás con tu usuario y listo.</p>
                </details>
                <details>
                    <summary>¿Puedo vender a crédito y cobrar después?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Sí. Registrás la venta a crédito y después cobrás en pagos parciales o completos. El sistema te muestra la deuda de cada cliente y qué ya está vencido.</p>
                </details>
                <details>
                    <summary>¿Sirve si tengo más de una sucursal?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Sí. Podés tener varias sucursales, cada una con sus cajas y depósitos, y mirar todo desde un solo lugar.</p>
                </details>
                <details>
                    <summary>¿Se puede cobrar en dólares o reales?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Sí, en la edición Completa. Guardás la cotización del día y el sistema calcula el equivalente en cada venta.</p>
                </details>
                <details>
                    <summary>¿Qué pasa con lo que cargue en la demo?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">La demo es un ambiente de prueba y se cierra a los {{ $dias }} días. Por eso no conviene cargar datos reales. Si querés avanzar, armamos tu sistema desde cero con la información de tu negocio.</p>
                </details>
                <details>
                    <summary>¿Cómo se cuida la información?<span class="plus" aria-hidden="true"></span></summary>
                    <p class="ans">Cada persona entra con su usuario y solo ve lo que su rol permite. Además, el sistema registra quién hizo cada acción importante y hace copias de respaldo de la base de datos.</p>
                </details>
            </div>
        </div>
    </section>
    <section class="sec" id="contacto">
        <div class="wrap">
            <div class="contacto rv">
                <div>
                    <h2>Contanos qué necesitás.</h2>
                    <p>Un sistema a medida, una página web, una app o dbstock con el equipo incluido. Te respondemos con una propuesta, sin compromiso.</p>
                </div>
                <div class="contacto-vias">
                    @if ($waLink)<a class="btn btn-primary" href="{{ $waLink }}" rel="noopener" target="_blank"><svg class="ic"><use href="#i-whatsapp-logo"/></svg>WhatsApp {{ $waVisible }}</a>@endif
                    @if ($correo)<a class="btn btn-ghost" href="mailto:{{ $correo }}"><svg class="ic"><use href="#i-envelope-simple"/></svg>{{ $correo }}</a>@endif
                </div>
            </div>
        </div>
    </section>
</main>

<footer class="foot">
    <div class="wrap">
        <div>
            <img class="logo-light" src="{{ asset('img/landing/logo-claro.png') }}" alt="Dobi Soluciones Informáticas" width="108" height="54">
            <img class="logo-dark" src="{{ asset('img/landing/logo-oscuro.png') }}" alt="" width="108" height="54">
            <p>Sistemas, páginas web y apps a medida, y dbstock para controlar ventas, stock y cobranzas.</p>
        </div>
        <div>
            <h4>Servicios</h4>
            <ul>
                <li><a href="#servicios">Sistemas a medida</a></li>
                <li><a href="#servicios">Sistema con equipo informático</a></li>
                <li><a href="#servicios">Páginas web y apps</a></li>
                <li><a href="#funciones">dbstock</a></li>
                <li><a href="#demo">Pedir demo</a></li>
            </ul>
        </div>
        <div>
            <h4>Acceso y contacto</h4>
            <ul>
                <li><a href="{{ \App\Support\Dominios::urlApp('/login') }}">Ingresar al sistema</a></li>
                @if ($wa)<li><a href="https://wa.me/{{ $wa }}" rel="noopener">WhatsApp {{ $waVisible }}</a></li>@endif
                @if ($correo)<li><a href="mailto:{{ $correo }}">{{ $correo }}</a></li>@endif
            </ul>
        </div>
        <div class="copy"><span>&copy; {{ date('Y') }} {{ $empresa }}. Todos los derechos reservados.</span><span>Hecho en Paraguay</span></div>
    </div>
</footer>

@if ($waLink)
<a class="wa-flotante" href="{{ $waLink }}" rel="noopener" target="_blank" aria-label="Escribinos por WhatsApp"><svg class="ic"><use href="#i-whatsapp-logo"/></svg></a>
@endif

<script src="{{ $v('js/landing.js') }}" defer></script>
</body>
</html>
