<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CuentaPagarController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CobranzaController;
use App\Http\Controllers\ControlProductoController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositoController;
use App\Http\Controllers\FinanzasController;
use App\Http\Controllers\IngresoEgresoController;
use App\Http\Controllers\MovimientoController;
use App\Http\Controllers\OperacionesClienteController;
use App\Http\Controllers\OperacionesController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PdvController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\PromocionController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteRentabilidadController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\Vendedor\AccesoController as VendedorAcceso;
use App\Http\Controllers\Vendedor\EstructuraController as VendedorEstructura;
use App\Http\Controllers\Vendedor\LicenciaController as VendedorLicencia;
use App\Http\Controllers\Vendedor\PanelController as VendedorPanel;
use Illuminate\Support\Facades\Route;

// Página de bienvenida e inicio
Route::get('/', [\App\Http\Controllers\LandingController::class, 'index'])->name('inicio');
Route::post('/demo', [\App\Http\Controllers\LandingController::class, 'demo'])->middleware('throttle:demo')->name('demo.solicitar');

/*
|--------------------------------------------------------------------------
| Rutas protegidas
|--------------------------------------------------------------------------
| 'auth'    -> hay que haber iniciado sesión.
| 'permiso' -> además el rol del usuario debe tener el permiso indicado
|              (tabla permisos / rol_permisos; se asignan en "Gestión de Roles").
|              Los roles de config/permisos.php (Administrador) pasan siempre.
*/
// Perfil propio: solo hace falta haber iniciado sesión (también se puede cambiar la clave con el plan en solo lectura).
Route::middleware('auth')->group(function () {
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'actualizar'])->name('perfil.actualizar');
    Route::put('/perfil/clave', [PerfilController::class, 'clave'])->name('perfil.clave');
    Route::get('/notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
});

Route::middleware(['auth', 'licencia'])->group(function () {

    // Inicio: lo ve cualquier usuario con sesión (es a donde llega tras el login)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---------------------------------------------------------------- Administración
    Route::middleware('permiso:USUARIOS_GESTIONAR')->group(function () {
        Route::post('usuarios/{usuario}/reactivar', [UsuarioController::class, 'reactivar'])->name('usuarios.reactivar');
        Route::resource('usuarios', UsuarioController::class);
        Route::resource('roles', RolController::class);
    });

    Route::middleware('permiso:CONFIG_GESTIONAR')->group(function () {
        Route::resource('sucursales', SucursalController::class);
        Route::resource('depositos', DepositoController::class)->middleware('modulo:depositos');
        Route::resource('cajas', CajaController::class);
        Route::resource('cotizaciones', CotizacionController::class);
        Route::get('/configuracion/terminal', function () {
            return view('configuracion.terminal');
        })->name('configuracion.terminal');
    });

    // ---------------------------------------------------------------- Inventario (historial de stock)
    Route::middleware('modulo:inventario')->group(function () {
    Route::get('/inventario', [InventarioController::class, 'index'])
        ->middleware('permiso:CATALOGO_GESTIONAR,STOCK_AJUSTAR')->name('inventario.index');
    Route::post('/inventario/movimiento', [InventarioController::class, 'registrar'])
        ->middleware('permiso:STOCK_AJUSTAR')->name('inventario.registrar');
    });

    // ---------------------------------------------------------------- Compras y cuentas a pagar
    Route::middleware('modulo:compras')->group(function () {
    Route::middleware('permiso:COMPRAS_REGISTRAR')->group(function () {
        Route::get('/compras/nueva', [CompraController::class, 'create'])->name('compras.create');
        Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
    });
    Route::middleware('permiso:COMPRAS_REGISTRAR,COMPRAS_ANULAR,PAGOS_PROVEEDORES')->group(function () {
        Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
        Route::get('/compras/{id}', [CompraController::class, 'show'])->whereNumber('id')->name('compras.show');
    });
    Route::middleware('permiso:COMPRAS_ANULAR')->group(function () {
        Route::post('/compras/{id}/anular', [CompraController::class, 'anular'])->whereNumber('id')->name('compras.anular');
        Route::post('/compras/{id}/devolver', [CompraController::class, 'devolver'])->whereNumber('id')->name('compras.devolver');
    });
    Route::get('/cuentas-pagar', [CuentaPagarController::class, 'index'])
        ->middleware('permiso:PAGOS_PROVEEDORES,COMPRAS_REGISTRAR,COMPRAS_ANULAR')->name('cuentas_pagar.index');
    Route::middleware('permiso:PAGOS_PROVEEDORES')->group(function () {
        Route::post('/cuentas-pagar/{id}/pagar', [CuentaPagarController::class, 'pagar'])->whereNumber('id')->name('cuentas_pagar.pagar');
        Route::post('/pagos-proveedores/{id}/anular', [CuentaPagarController::class, 'anularPago'])->whereNumber('id')->name('pagos_proveedores.anular');
    });
    });

    // ---------------------------------------------------------------- Presupuestos
    Route::middleware('modulo:presupuestos')->group(function () {
    Route::middleware('permiso:PRESUPUESTOS_GESTIONAR')->group(function () {
        Route::get('/presupuestos/nuevo', [PresupuestoController::class, 'create'])->name('presupuestos.create');
        Route::post('/presupuestos', [PresupuestoController::class, 'store'])->name('presupuestos.store');
        Route::get('/presupuestos/{id}/editar', [PresupuestoController::class, 'edit'])->whereNumber('id')->name('presupuestos.edit');
        Route::put('/presupuestos/{id}', [PresupuestoController::class, 'update'])->whereNumber('id')->name('presupuestos.update');
        Route::post('/presupuestos/{id}/estado', [PresupuestoController::class, 'estado'])->whereNumber('id')->name('presupuestos.estado');
        Route::post('/presupuestos/{id}/renovar', [PresupuestoController::class, 'renovar'])->whereNumber('id')->name('presupuestos.renovar');
    });
    // Quien vende en caja también ve el listado y puede convertir en venta (desde el punto de venta).
    Route::middleware('permiso:PRESUPUESTOS_GESTIONAR,PDV_USAR')->group(function () {
        Route::get('/presupuestos', [PresupuestoController::class, 'index'])->name('presupuestos.index');
        Route::get('/presupuestos/{id}', [PresupuestoController::class, 'show'])->whereNumber('id')->name('presupuestos.show');
        Route::get('/presupuestos/{id}/imprimir', [PresupuestoController::class, 'imprimir'])->whereNumber('id')->name('presupuestos.imprimir');
    });
    });

    // ---------------------------------------------------------------- Catálogo
    Route::middleware('permiso:CATALOGO_GESTIONAR')->group(function () {
        Route::resource('productos', ProductoController::class);
        Route::resource('categorias', CategoriaController::class);
        Route::resource('proveedores', ProveedorController::class)->middleware('modulo:compras');
        Route::resource('promociones', PromocionController::class)->middleware('modulo:promociones');
        Route::post('promociones/toggle/{id}', [PromocionController::class, 'toggleEstado'])->middleware('modulo:promociones')->name('promociones.toggle');
        Route::get('/operaciones/productos-control', [ControlProductoController::class, 'index'])->name('productos.control');
        Route::post('/operaciones/productos-control/toggle/{id}', [ControlProductoController::class, 'toggleEstado']);
    });

    // ---------------------------------------------------------------- Clientes
    Route::resource('clientes', ClienteController::class)->middleware('permiso:CLIENTES_GESTIONAR');

    Route::middleware(['permiso:CLIENTES_CREDITO', 'modulo:cobranzas'])->group(function () {
        Route::get('/operaciones/clientes-control', [OperacionesClienteController::class, 'index'])->name('operaciones.clientes_control');
        Route::post('/operaciones/clientes-control/toggle/{id}', [OperacionesClienteController::class, 'toggleEstado'])->name('operaciones.clientes_control.toggle');
    });

    // ---------------------------------------------------------------- Punto de venta
    Route::middleware('permiso:PDV_USAR')->group(function () {
        Route::get('/pdv', [PdvController::class, 'index'])->name('pdv.index');
        Route::post('/pdv/store', [PdvController::class, 'store'])->name('pdv.store');
        Route::post('/pdv/cliente-ajax', [PdvController::class, 'storeClienteAjax'])->name('pdv.cliente.ajax');
        Route::get('/pdv/ticket-simple/{id}', [PdvController::class, 'ticketSimple']);
        Route::get('/pdv/ticket-factura/{id}', [PdvController::class, 'ticketFactura']);
    });

    // ---------------------------------------------------------------- Cobranzas
    Route::middleware('modulo:cobranzas')->group(function () {
    Route::middleware('permiso:COBRANZAS_REGISTRAR')->group(function () {
        Route::get('/cobranzas', [CobranzaController::class, 'index'])->name('cobranzas.index');
        Route::post('/cobranzas/store', [CobranzaController::class, 'store'])->name('cobranzas.store');
        Route::get('/cobranzas/ticket/{id}', [CobranzaController::class, 'imprimirTicket'])->name('cobranzas.ticket');
        Route::get('/cobranzas/historial', [CobranzaController::class, 'historial'])->name('cobranzas.historial');
    });
    Route::post('/cobranzas/{id}/anular', [CobranzaController::class, 'anular'])
        ->middleware('permiso:COBRANZAS_ANULAR')->name('cobranzas.anular');
    });

    // ---------------------------------------------------------------- Auditoría
    Route::get('/auditoria', [AuditoriaController::class, 'index'])
        ->middleware(['permiso:AUDITORIA_VER', 'modulo:auditoria'])->name('auditoria.index');

    // ---------------------------------------------------------------- Ventas (historial y correcciones)
    Route::middleware('permiso:VENTAS_HISTORIAL')->group(function () {
        Route::get('/operaciones/ventas', [OperacionesController::class, 'historialVentas'])->name('operaciones.ventas');
        Route::get('/operaciones/ventas/excel', [OperacionesController::class, 'exportarExcel'])->name('operaciones.ventas.excel');
        Route::get('/operaciones/ventas/pdf', [OperacionesController::class, 'exportarPdf'])->name('operaciones.ventas.pdf');
        Route::get('/operaciones/ventas/{id}/ticket', [OperacionesController::class, 'reimprimir'])->name('operaciones.ventas.ticket')->whereNumber('id');
    });
    Route::post('/operaciones/ventas/{id}/anular', [OperacionesController::class, 'anularVenta'])
        ->middleware('permiso:VENTAS_ANULAR')->name('operaciones.ventas.anular');
    Route::post('/operaciones/ventas/{id}/devolver', [OperacionesController::class, 'procesarDevolucion'])
        ->middleware('permiso:VENTAS_DEVOLVER')->name('operaciones.ventas.devolver');

    // ---------------------------------------------------------------- Finanzas y cajas
    // Quien abre/cierra caja necesita ver la pantalla de cajas; el resto de reportes exige FINANZAS_VER.
    Route::get('/finanzas', [FinanzasController::class, 'index'])
        ->middleware('permiso:FINANZAS_VER,CAJA_ABRIR_CERRAR')->name('finanzas.index');

    Route::middleware('permiso:CAJA_ABRIR_CERRAR')->group(function () {
        Route::get('/finanzas/apertura', [FinanzasController::class, 'aperturaForm'])->name('finanzas.apertura.form');
        Route::post('/finanzas/apertura', [FinanzasController::class, 'aperturaStore'])->name('finanzas.apertura.store');
        Route::post('/finanzas/cerrar/{ses_id}', [FinanzasController::class, 'cerrarCaja'])->name('finanzas.cerrarCaja');
    });

    Route::post('/finanzas/transferir', [FinanzasController::class, 'transferir'])
        ->middleware('permiso:CAJA_TRANSFERIR')->name('finanzas.transferir');

    Route::middleware('permiso:FINANZAS_VER')->group(function () {
        Route::get('/finanzas/movimientos', [MovimientoController::class, 'index'])->name('finanzas.movimientos');
        Route::get('/finanzas/cierres', [FinanzasController::class, 'cierres'])->name('finanzas.cierres');
        Route::get('/finanzas/ingresos-egresos', [IngresoEgresoController::class, 'index'])->name('finanzas.ingresos_egresos');
    });

    Route::post('/finanzas/ingresos-egresos/store', [IngresoEgresoController::class, 'store'])
        ->middleware('permiso:CAJA_INGRESO_EGRESO')->name('finanzas.ingresos_egresos.store');

    // ---------------------------------------------------------------- Reportes
    Route::middleware(['permiso:REPORTES_VER', 'modulo:reportes_avanzados'])->group(function () {
        Route::get('/operaciones/reporte-abc', [ReporteRentabilidadController::class, 'index'])->name('operaciones.reporte_abc');
        Route::get('/operaciones/reporte-abc/excel', [ReporteRentabilidadController::class, 'exportarExcel'])->name('operaciones.reporte_abc.excel');
        Route::get('/operaciones/reporte-abc/pdf', [ReporteRentabilidadController::class, 'exportarPdf'])->name('operaciones.reporte_abc.pdf');
    });
});

/*
|--------------------------------------------------------------------------
| Panel del vendedor del sistema
|--------------------------------------------------------------------------
| Acceso privado con clave propia (VENDEDOR_CLAVE_HASH en .env), separado de los usuarios del negocio.
| Queda a propósito FUERA del grupo 'auth' y de 'licencia': el vendedor siempre puede entrar a arreglar un plan.
*/
Route::prefix(config('vendedor.ruta', 'panel-vendedor'))->name('vendedor.')->group(function () {
    Route::get('/entrar', [VendedorAcceso::class, 'formulario'])->name('login');
    Route::post('/entrar', [VendedorAcceso::class, 'entrar'])->name('entrar');

    Route::middleware('vendedor')->group(function () {
        Route::post('/salir', [VendedorAcceso::class, 'salir'])->name('salir');
        Route::get('/', [VendedorPanel::class, 'resumen'])->name('resumen');

        Route::get('/negocio', [VendedorPanel::class, 'negocio'])->name('negocio');
        Route::post('/negocio', [VendedorPanel::class, 'guardarNegocio'])->name('negocio.guardar');

        Route::get('/modulos', [VendedorPanel::class, 'modulos'])->name('modulos');
        Route::post('/modulos', [VendedorPanel::class, 'guardarModulos'])->name('modulos.guardar');
        Route::post('/modulos/edicion', [VendedorPanel::class, 'aplicarEdicion'])->name('modulos.edicion');

        Route::get('/herramientas', [VendedorPanel::class, 'herramientas'])->name('herramientas');
        Route::post('/herramientas/preparar', [VendedorPanel::class, 'prepararBase'])->name('herramientas.preparar');
        Route::post('/herramientas/verificar', [VendedorPanel::class, 'verificar'])->name('herramientas.verificar');
        Route::post('/herramientas/cache', [VendedorPanel::class, 'limpiarCache'])->name('herramientas.cache');

        Route::get('/estructura', [VendedorEstructura::class, 'index'])->name('estructura');
        Route::post('/sucursales', [VendedorEstructura::class, 'crearSucursal'])->name('sucursales.crear');
        Route::post('/sucursales/{id}/estado', [VendedorEstructura::class, 'estadoSucursal'])->whereNumber('id')->name('sucursales.estado');
        Route::post('/cajas', [VendedorEstructura::class, 'crearCaja'])->name('cajas.crear');
        Route::post('/cajas/{id}/estado', [VendedorEstructura::class, 'estadoCaja'])->whereNumber('id')->name('cajas.estado');
        Route::post('/depositos', [VendedorEstructura::class, 'crearDeposito'])->name('depositos.crear');
        Route::post('/depositos/{id}/estado', [VendedorEstructura::class, 'estadoDeposito'])->whereNumber('id')->name('depositos.estado');
        Route::post('/cotizacion', [VendedorEstructura::class, 'cotizacion'])->name('cotizacion');

        Route::get('/usuarios', [VendedorEstructura::class, 'usuarios'])->name('usuarios');
        Route::post('/usuarios/administrador', [VendedorEstructura::class, 'crearAdministrador'])->name('usuarios.administrador');
        Route::post('/usuarios/{id}/clave', [VendedorEstructura::class, 'restablecerClave'])->whereNumber('id')->name('usuarios.clave');

        Route::get('/planes', [VendedorLicencia::class, 'planes'])->name('planes');
        Route::post('/planes', [VendedorLicencia::class, 'guardarPlan'])->name('planes.crear');
        Route::put('/planes/{id}', [VendedorLicencia::class, 'guardarPlan'])->whereNumber('id')->name('planes.editar');
        Route::post('/planes/{id}/estado', [VendedorLicencia::class, 'estadoPlan'])->whereNumber('id')->name('planes.estado');

        Route::get('/licencia', [VendedorLicencia::class, 'licencia'])->name('licencia');
        Route::post('/licencia/plan', [VendedorLicencia::class, 'aplicarPlan'])->name('licencia.plan');
        Route::post('/licencia/ajustes', [VendedorLicencia::class, 'ajustes'])->name('licencia.ajustes');
        Route::post('/licencia/pagos', [VendedorLicencia::class, 'registrarPago'])->name('licencia.pagos');
        Route::post('/licencia/pagos/{id}/anular', [VendedorLicencia::class, 'anularPago'])->whereNumber('id')->name('licencia.pagos.anular');
    });
});

require __DIR__.'/auth.php';
