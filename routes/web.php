<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdvController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\DepositoController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\FinanzasController; // <-- Asegúrate de importar el nuevo controlador

// Página de bienvenida e inicio
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    // ... tus otras rutas ...
    Route::resource('cajas', App\Http\Controllers\CajaController::class);
});

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    // Módulos principales y de configuración
    Route::resource('usuarios', UsuarioController::class);
    Route::resource('clientes', ClienteController::class);
    Route::resource('sucursales', SucursalController::class);
    Route::resource('proveedores', ProveedorController::class);
    Route::resource('categorias', CategoriaController::class);
    Route::resource('depositos', DepositoController::class);
    Route::resource('productos', ProductoController::class);

    // Módulo Punto de Venta (PDV)
    Route::get('/pdv', [PdvController::class, 'index'])->name('pdv.index');
    Route::post('/pdv/store', [PdvController::class, 'store'])->name('pdv.store');
    Route::post('/pdv/cliente-ajax', [PdvController::class, 'storeClienteAjax'])->name('pdv.cliente.ajax');

    // Módulo de Finanzas y Cajas
    Route::get('/finanzas', [FinanzasController::class, 'index'])->name('finanzas.index');
    Route::get('/finanzas/apertura', [FinanzasController::class, 'aperturaForm'])->name('finanzas.apertura.form');
    Route::post('/finanzas/apertura', [FinanzasController::class, 'aperturaStore'])->name('finanzas.apertura.store');
    Route::get('/finanzas/movimientos', [FinanzasController::class, 'movimientos'])->name('finanzas.movimientos');
    Route::get('/finanzas/cierres', [FinanzasController::class, 'cierres'])->name('finanzas.cierres');
    Route::post('/finanzas/transferir', [FinanzasController::class, 'transferir'])->name('finanzas.transferir');
    Route::post('/finanzas/cerrar/{ses_id}', [FinanzasController::class, 'cerrarCaja'])->name('finanzas.cerrarCaja');

    // Operaciones - Control de Clientes
    Route::get('/operaciones/clientes-control', [App\Http\Controllers\OperacionesClienteController::class, 'index'])->name('operaciones.clientes_control');
    Route::post('/operaciones/clientes-control/toggle/{id}', [App\Http\Controllers\OperacionesClienteController::class, 'toggleEstado'])->name('operaciones.clientes_control.toggle');

    Route::resource('cotizaciones', App\Http\Controllers\CotizacionController::class)->middleware('auth');

    Route::get('/operaciones/ventas', [App\Http\Controllers\OperacionesController::class, 'historialVentas'])->name('operaciones.ventas');
    Route::post('/operaciones/ventas/{id}/anular', [App\Http\Controllers\OperacionesController::class, 'anularVenta'])->name('operaciones.ventas.anular');
    Route::get('/operaciones/ventas/excel', [App\Http\Controllers\OperacionesController::class, 'exportarExcel'])->name('operaciones.ventas.excel');
    Route::get('/operaciones/ventas/pdf', [App\Http\Controllers\OperacionesController::class, 'exportarPdf'])->name('operaciones.ventas.pdf');
    Route::post('/operaciones/ventas/{id}/devolver', [App\Http\Controllers\OperacionesController::class, 'procesarDevolucion'])->name('operaciones.ventas.devolver');

    Route::get('/operaciones/productos-control', [App\Http\Controllers\ControlProductoController::class, 'index'])->name('productos.control');
    Route::post('/operaciones/productos-control/toggle/{id}', [App\Http\Controllers\ControlProductoController::class, 'toggleEstado']);
    Route::resource('promociones', App\Http\Controllers\PromocionController::class);
    Route::get('promociones/toggle/{id}', [App\Http\Controllers\PromocionController::class, 'toggleEstado'])->name('promociones.toggle');

    Route::get('/finanzas/ingresos-egresos', [App\Http\Controllers\IngresoEgresoController::class, 'index'])->name('finanzas.ingresos_egresos');
    Route::post('/finanzas/ingresos-egresos/store', [App\Http\Controllers\IngresoEgresoController::class, 'store'])->name('finanzas.ingresos_egresos.store');
    Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/configuracion/terminal', function () {
        return view('configuracion.terminal');
    })->name('configuracion.terminal')->middleware('auth');

    Route::get('/pdv/ticket-simple/{id}', [PdvController::class, 'ticketSimple']);
    Route::get('/pdv/ticket-factura/{id}', [PdvController::class, 'ticketFactura']);

    Route::get('/cobranzas', [App\Http\Controllers\CobranzaController::class, 'index'])->name('cobranzas.index');
    Route::post('/cobranzas/store', [App\Http\Controllers\CobranzaController::class, 'store'])->name('cobranzas.store');
    Route::get('/cobranzas/ticket/{id}', [App\Http\Controllers\CobranzaController::class, 'imprimirTicket'])->name('cobranzas.ticket');

    Route::middleware('auth')->group(function () {
        Route::get('/finanzas/movimientos', [App\Http\Controllers\MovimientoController::class, 'index'])->name('finanzas.movimientos');
    });

    Route::get('/operaciones/reporte-abc', [App\Http\Controllers\ReporteRentabilidadController::class, 'index'])->name('operaciones.reporte_abc');
    Route::get('/operaciones/reporte-abc/excel', [App\Http\Controllers\ReporteRentabilidadController::class, 'exportarExcel'])->name('operaciones.reporte_abc.excel');
    Route::get('/operaciones/reporte-abc/pdf', [App\Http\Controllers\ReporteRentabilidadController::class, 'exportarPdf'])->name('operaciones.reporte_abc.pdf');

});

require __DIR__.'/auth.php';