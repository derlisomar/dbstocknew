<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\CuentasCobrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PdvController extends Controller
{

    public function index()
    {
        $productos = \App\Models\Producto::where('pro_activo', true)->get();
        $clientes = \App\Models\Cliente::all();
        
        $activa = \App\Models\Cotizacion::where('cot_activa', true)->first();
        $cotizaciones = [
            'USD' => $activa ? (float) $activa->cot_dolar : 7500,
            'BRL' => $activa ? (float) $activa->cot_real : 1500
        ];

        $hoy = now()->toDateString();
        $promociones = \App\Models\Promocion::where('prom_activa', true)
                        ->where('prom_fecha_fin', '>=', $hoy)
                        ->get();

        foreach ($productos as $producto) {
            $producto->en_promocion = false;
            // Forzamos a que sean valores numéricos seguros
            $producto->precio_promocional = (float) $producto->pro_precioventa; 
            $producto->pro_preciomayorista = (float) $producto->pro_preciomayorista;

            $promo = $promociones->where('prom_aplica_a', 'PRODUCTO')->where('pro_id', $producto->pro_id)->first() 
                     ?? $promociones->where('prom_aplica_a', 'CATEGORIA')->where('cat_id', $producto->cat_id)->first();

            if ($promo) {
                $producto->en_promocion = true;
                if ($promo->prom_tipo_descuento == 'PORCENTAJE') {
                    $descuento = $producto->pro_precioventa * ($promo->prom_valor / 100);
                    $producto->precio_promocional = (float) ($producto->pro_precioventa - $descuento);
                } else {
                    // Si es MONTO, se descuenta ese valor
                    $producto->precio_promocional = (float) ($producto->pro_precioventa - $promo->prom_valor); 
                }
            }
        }

        return view('pdv.index', compact('productos', 'clientes', 'cotizaciones'));
    }

 public function store(Request $request)
    {
        $request->validate([
            'cli_id' => 'required|exists:clientes,cli_id',
            'vta_tipo' => 'required|in:CONTADO,CREDITO',
            'forma_pago' => 'required|in:EFECTIVO,TRANSFERENCIA',
            'nro_transferencia' => 'nullable|required_if:forma_pago,TRANSFERENCIA|string',
            'moneda' => 'required|in:GS,USD,BRL',
            'carrito' => 'required|array|min:1',
            'caj_id' => 'nullable'
        ]);

        // =========================================================
        // NUEVAS VALIDACIONES ESTRICTAS DE CLIENTE Y CRÉDITO
        // =========================================================
        // Traemos al cliente sumando todas sus deudas pendientes actuales
        $cliente = \App\Models\Cliente::withSum('cuentasCobrar as total_deuda', 'cred_saldo_pendiente')->find($request->cli_id);

        // REGLA 1: Si el cliente está bloqueado, se rechaza cualquier tipo de venta
     if ($cliente->cli_bloqueado) {
        return response()->json([
            'success' => false, 
            'message' => 'OPERACIÓN DENEGADA: El cliente se encuentra bloqueado por la administración.'
        ]);
     }

        // REGLAS PARA VENTAS A CRÉDITO
        if ($request->vta_tipo === 'CREDITO') {
        
        // REGLA 2: Verificar si tiene el crédito prohibido
        if (!$cliente->cli_permitir_credito) {
            return response()->json([
                'success' => false, 
                'message' => 'OPERACIÓN DENEGADA: Este cliente no está habilitado para realizar compras a crédito.'
            ]);
        }

        // REGLA 3: Verificar que no supere su límite de crédito (si tiene uno asignado mayor a 0)
        $totalGs = collect($request->carrito)->sum(function($item) {
            // Si viene subtotal lo usa, sino lo calcula multiplicando cantidad por precio
            return $item['subtotal'] ?? ($item['cantidad'] * $item['precio']);
        });
        $deudaActual = $cliente->total_deuda ?? 0;
        $limite = $cliente->cli_limite_credito ?? 0;

        if ($limite > 0 && ($deudaActual + $totalGs) > $limite) {
            $disponible = $limite - $deudaActual;
            return response()->json([
                'success' => false, 
                'message' => 'LÍMITE EXCEDIDO: El cliente superó su línea de crédito autorizada.' . "\n\n" .
                             'Límite: Gs. ' . number_format($limite, 0, ',', '.') . "\n" .
                             'Crédito Disponible: Gs. ' . number_format($disponible > 0 ? $disponible : 0, 0, ',', '.') . "\n" .
                             'Esta Venta: Gs. ' . number_format($totalGs, 0, ',', '.')
            ]);
        }
        }
        // =========================================================

  try {
    DB::beginTransaction();

    $usuario = Auth::user();
    $cajIdFisico = $request->input('caj_id'); // Capturamos la caja del navegador

    // 1. ARMAMOS LA ÚNICA CONSULTA DE SESIÓN (con relación a sucursal incluida)
    $query = \App\Models\CajaSesion::with(['caja.sucursal'])
                             ->where('usu_id', $usuario->usu_id ?? 1)
                             ->where('ses_estado', 'ABIERTA');

    // Obligamos a que se guarde en la caja de esta computadora 
    if (!empty($cajIdFisico)) {
        $query->where('caj_id', $cajIdFisico);
    }

    $sesionActiva = $query->first();

    // Bloqueamos si no hay sesión abierta en esta caja específica
    if (!$sesionActiva) {
        return response()->json([
            'success' => false, 
            'message' => 'El equipo está configurado para una caja, pero no tiene una sesión ABIERTA en este momento.'
        ], 422);
    }

    $cajaFisica = $sesionActiva->caja;
    $sucursal = $cajaFisica->sucursal;
    $sesId = $sesionActiva->ses_id;
    $sucId = $cajaFisica->suc_id ?? 1;

    $totalGs = collect($request->carrito)->sum(fn($item) => $item['subtotal']);
    $monedaVenta = $request->moneda ?? 'GS'; 

    // 2. PREPARAR DATOS FISCALES (Solo consume folio si la caja emite factura)
    $nro_factura = null;
    $timbrado = null;
    
    if ($cajaFisica->caj_tipo_impresion === 'TICKET_FACTURA' && $sucursal) {
        $timbrado = $sucursal->suc_timbrado;
        $nro_factura = str_pad($sucursal->suc_factura_secuencia, 7, '0', STR_PAD_LEFT);
        // Incrementamos la secuencia para la próxima venta
        $sucursal->increment('suc_factura_secuencia');
    }

    // 3. CREAR LA VENTA (Inicialmente con ceros en IVA, luego se actualiza)
    $venta = Venta::create([
        'suc_id' => $sucId,
        'cli_id' => $request->cli_id,
        'usu_id' => $usuario->usu_id ?? 1,
        'ses_id' => $sesId,
        'vta_tipo' => $request->vta_tipo,
        'vta_total' => $totalGs,
        'vta_timbrado' => $timbrado,
        'vta_nro_factura' => $nro_factura,
        'vta_estado' => 'CONFIRMADA'
    ]);

    // Variables para acumular IVA
    $total_exenta = 0;
    $total_iva5 = 0;
    $total_iva10 = 0;

    // 4. REGISTRAR DETALLES, DESCONTAR STOCK Y LIQUIDAR IVA
    foreach ($request->carrito as $item) {
        DetalleVenta::create([
            'vta_id' => $venta->vta_id,
            'pro_id' => $item['pro_id'],
            'det_cantidad' => $item['cantidad'],
            'det_preciounitario' => $item['precio'],
            'det_subtotal' => $item['subtotal']
        ]);

        // Descontar stock y calcular IVA por tipo de producto
        $producto = Producto::find($item['pro_id']);
        if ($producto) {
            $producto->pro_stockactual -= $item['cantidad'];
            $producto->save();
            
            // Lógica fiscal (IVA incluido)
            if ($producto->pro_tipo_iva == 10) {
                $total_iva10 += $item['subtotal'] / 11;
            } elseif ($producto->pro_tipo_iva == 5) {
                $total_iva5 += $item['subtotal'] / 21;
            } else {
                $total_exenta += $item['subtotal'];
            }
        }
    }

    // 5. ACTUALIZAR LA VENTA CON LOS CÁLCULOS EXACTOS DE IVA
    $venta->update([
        'vta_total_exenta' => round($total_exenta, 2),
        'vta_total_iva5' => round($total_iva5, 2),
        'vta_total_iva10' => round($total_iva10, 2),
    ]);

    // 6. REGISTRAR EL INGRESO EN LA CAJA Y SUMAR AL SALDO CONSOLIDADO
    \App\Models\CajaMovimiento::create([
        'ses_id' => $sesionActiva->ses_id,
        'mov_tipo' => 'INGRESO',
        'mov_monto' => $totalGs,
        'mov_concepto' => 'Venta Nro. ' . $venta->vta_id . ' (' . ($request->forma_pago ?? 'CONTADO') . ')',
        'mov_moneda' => $monedaVenta
    ]);

    $columnaSaldo = 'caj_saldo_' . strtolower($monedaVenta);

    if (isset($cajaFisica->$columnaSaldo)) {
        $cajaFisica->increment($columnaSaldo, $totalGs);
    }

    // 7. SI ES A CRÉDITO, GENERAR CUENTA POR COBRAR
    if ($request->vta_tipo === 'CREDITO') {
        CuentasCobrar::create([
            'vta_id' => $venta->vta_id,
            'cli_id' => $request->cli_id,
            'cred_monto_total' => $totalGs,
            'cred_saldo_pendiente' => $totalGs,
            'cred_fecha_vencimiento' => now()->addDays(30),
            'cred_estado' => 'PENDIENTE'
        ]);
    }

    DB::commit();
    return response()->json([
        'success' => true,
        'message' => '¡Venta registrada con éxito y stock actualizado!',
        'impresora' => $cajaFisica->caj_impresora ?? null,
        'venta_id' => $venta->vta_id,
        'tipo_impresion' => $cajaFisica->caj_tipo_impresion
    ]);

} catch (\Exception $e) {
    DB::rollBack();
    return response()->json(['success' => false, 'message' => 'Error al procesar la venta: ' . $e->getMessage()], 500);
}

    }

    public function storeClienteAjax(Request $request)
    {
        $request->validate([
            'cli_ruc_ci' => 'required|string|max:30|unique:clientes,cli_ruc_ci',
            'cli_nombre' => 'required|string|max:100',
            'cli_apellido' => 'required|string|max:100', // Ahora es obligatorio
            'cli_telefono' => 'required|string|max:30',  // Ahora es obligatorio
            'cli_email' => 'required|email|max:100',     // Ahora es obligatorio
            'cli_direccion' => 'required|string',        // Ahora es obligatorio
            'cli_limite_credito' => 'nullable|numeric|min:0',
        ]);
        
        $data = $request->all();
        $data['cli_es_mayorista'] = $request->has('cli_es_mayorista');
        $cliente = \App\Models\Cliente::create($data);

        return response()->json(['success' => true, 'cliente' => $cliente]);
    }

public function ticketSimple($id)
{
    // Cambiamos 'caja' por 'sesion.caja' porque la caja cuelga de la sesión de caja
    $venta = Venta::with(['detalles.producto', 'cliente', 'sesion.caja', 'sucursal'])->findOrFail($id);
    
    return view('pdv.ticket-simple', compact('venta'));
}

public function ticketFactura($id)
{
    $venta = Venta::with(['detalles.producto', 'cliente', 'sesion.caja', 'sucursal'])->findOrFail($id);
    
    return view('pdv.ticket-factura', compact('venta'));
}

}