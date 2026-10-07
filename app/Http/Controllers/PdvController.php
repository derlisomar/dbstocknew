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
        // 1. VALIDACIONES DE REQUEST
        $request->validate([
            'cli_id' => 'required|exists:clientes,cli_id',
            'vta_tipo' => 'required|in:CONTADO,CREDITO',
            'forma_pago' => 'required|in:EFECTIVO,TRANSFERENCIA,TARJETA_CREDITO,TARJETA_DEBITO,QR',
            'nro_transferencia' => 'nullable|required_unless:forma_pago,EFECTIVO|string',
            'moneda' => 'required|in:GS,USD,BRL',
            'carrito' => 'required|array|min:1',
            'caj_id' => 'nullable' // Recibe la caja física de la terminal local
        ]);

        $totalGs = collect($request->carrito)->sum(fn($item) => $item['subtotal']);

        // =========================================================
        // 2. VALIDACIONES ESTRICTAS DE CLIENTE Y LÍNEA DE CRÉDITO 
        // =========================================================
        $cliente = \App\Models\Cliente::withSum('cuentasCobrar as total_deuda', 'cred_saldo_pendiente')->find($request->cli_id);

        if ($cliente && $cliente->cli_bloqueado) {
            return response()->json(['success' => false, 'message' => 'OPERACIÓN DENEGADA: El cliente se encuentra bloqueado por la administración.'], 422);
        }

        if ($request->vta_tipo === 'CREDITO') {
            if ($cliente && !$cliente->cli_permitir_credito) {
                return response()->json(['success' => false, 'message' => 'OPERACIÓN DENEGADA: Este cliente no está habilitado para realizar compras a crédito.'], 422);
            }

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
                ], 422);
            }
        }
        // =========================================================

        try {
            // 3. INICIO DE LA TRANSACCIÓN AUTOMÁTICA SEGURA
            // DB::transaction ejecuta el Commit automático al terminar, o Rollback si se lanza una Excepción.
            $resultadoVenta = DB::transaction(function () use ($request, $totalGs) {
                $usuario = Auth::user();
                $cajIdEquipo = $request->input('caj_id'); 
                $monedaVenta = $request->moneda ?? 'GS';

                // A. OBTENER SESIÓN FILTRADA ESTRICTAMENTE POR LA CAJA (CON BLOQUEO)
                $query = \App\Models\CajaSesion::with(['caja.sucursal'])
                                         ->where('usu_id', $usuario->usu_id ?? 1)
                                         ->where('ses_estado', 'ABIERTA');

                if (!empty($cajIdEquipo)) {
                    $query->where('caj_id', $cajIdEquipo);
                }

                $sesionActiva = $query->lockForUpdate()->first();

                // Si no hay sesión, lanzamos una excepción para abortar la transacción
                if (!$sesionActiva) {
                    throw new \Exception('El equipo está configurado para una caja específica, pero no tiene una sesión ABIERTA en este momento.');
                }

                $cajaFisica = $sesionActiva->caja;
                $sucursalId = $cajaFisica->suc_id ?? 1;

                // B. BLOQUEO DE CONCURRENCIA PARA LA SECUENCIA DE FACTURACIÓN
                $sucursal = \App\Models\Sucursal::where('suc_id', $sucursalId)->lockForUpdate()->first();

                $nro_factura = null;
                $timbrado = null;
                
                if ($cajaFisica->caj_tipo_impresion === 'TICKET_FACTURA' && $sucursal) {
                    $timbrado = $sucursal->suc_timbrado;
                    $nro_factura = str_pad($sucursal->suc_factura_secuencia, 7, '0', STR_PAD_LEFT);
                    $sucursal->increment('suc_factura_secuencia');
                }

                // C. CREAR LA VENTA CON TODOS SUS CAMPOS FISCALES Y DE PAGO
                $venta = \App\Models\Venta::create([
                    'suc_id' => $sucursalId,
                    'cli_id' => $request->cli_id,
                    'usu_id' => $usuario->usu_id ?? 1,
                    'ses_id' => $sesionActiva->ses_id,
                    'vta_tipo' => $request->vta_tipo,
                    'vta_formapago' => $request->forma_pago,       
                    'nro_transferencia' => $request->nro_transferencia, 
                    'vta_moneda' => $monedaVenta,             
                    'vta_total' => $totalGs,
                    'vta_timbrado' => $timbrado,
                    'vta_nro_factura' => $nro_factura,
                    'vta_estado' => 'CONFIRMADA'
                ]);

                $total_exenta = 0;
                $total_iva5 = 0;
                $total_iva10 = 0;

                // D. REGISTRAR DETALLES, DESCONTAR STOCK E INCLUIR COSTO HISTÓRICO
                foreach ($request->carrito as $item) {
                    $producto = \App\Models\Producto::find($item['pro_id']);
                    $costoUnitario = $producto ? ($producto->pro_preciocosto ?? 0) : 0;

                    \App\Models\DetalleVenta::create([
                        'vta_id' => $venta->vta_id,
                        'pro_id' => $item['pro_id'],
                        'det_cantidad' => $item['cantidad'],
                        'det_preciounitario' => $item['precio'],
                        'det_subtotal' => $item['subtotal'],
                        'det_preciocosto' => $costoUnitario 
                    ]);

                    if ($producto) {
                        $producto->decrement('pro_stockactual', $item['cantidad']);
                        
                        // Liquidación de impuestos
                        if ($producto->pro_tipo_iva == 10) {
                            $total_iva10 += $item['subtotal'] / 11;
                        } elseif ($producto->pro_tipo_iva == 5) {
                            $total_iva5 += $item['subtotal'] / 21;
                        } else {
                            $total_exenta += $item['subtotal'];
                        }
                    }
                }

                // E. ACTUALIZAR TOTALES DE IMPUESTOS EN LA VENTA
                $venta->update([
                    'vta_total_exenta' => round($total_exenta, 2),
                    'vta_total_iva5' => round($total_iva5, 2),
                    'vta_total_iva10' => round($total_iva10, 2),
                ]);

                // F. INGRESO EN LA CAJA Y CONVERSIÓN DE MONEDA (Solo si es al Contado)
                if ($request->vta_tipo === 'CONTADO') {
                    $montoIngresoCaja = $totalGs;
                    
                    // Convertir el monto físico si la venta fue en USD o BRL
                    if ($monedaVenta !== 'GS') {
                        $cotizacion = \App\Models\Cotizacion::where('cot_activa', true)->first();
                        if ($monedaVenta === 'USD') {
                            $montoIngresoCaja = $totalGs / ($cotizacion->cot_dolar ?? 7500);
                        } elseif ($monedaVenta === 'BRL') {
                            $montoIngresoCaja = $totalGs / ($cotizacion->cot_real ?? 1500);
                        }
                    }

                    \App\Models\CajaMovimiento::create([
                        'ses_id' => $sesionActiva->ses_id,
                        'mov_tipo' => 'INGRESO',
                        'mov_monto' => $montoIngresoCaja, // Guardamos el billete convertido
                        'mov_concepto' => 'Venta Nro. ' . ($nro_factura ?? $venta->vta_id) . ' (' . $request->forma_pago . ')',
                        'mov_moneda' => $monedaVenta
                    ]);

                    $columnaSaldo = 'caj_saldo_' . strtolower($monedaVenta);
                    
                    // Bloqueamos la caja para evitar fallos si dos personas cobran a la vez
                    $cajaUpdate = \App\Models\Caja::where('caj_id', $cajaFisica->caj_id)->lockForUpdate()->first();
                    if ($cajaUpdate && isset($cajaUpdate->$columnaSaldo)) {
                        $cajaUpdate->increment($columnaSaldo, $montoIngresoCaja);
                    }
                }

                // G. SI ES A CRÉDITO, GENERAR CUENTA POR COBRAR (Sin tocar el saldo de caja)
                if ($request->vta_tipo === 'CREDITO') {
                    \App\Models\CuentasCobrar::create([
                        'vta_id' => $venta->vta_id,
                        'cli_id' => $request->cli_id,
                        'cred_monto_total' => $totalGs, // La deuda es en Guaraníes siempre
                        'cred_saldo_pendiente' => $totalGs,
                        'cred_fecha_vencimiento' => now()->addDays(30),
                        'cred_estado' => 'PENDIENTE'
                    ]);
                }

                // H. RETORNO EXITOSO DE LA TRANSACCIÓN
                return [
                    'success' => true, 
                    'message' => '¡Venta registrada con éxito, stock y correlativo actualizados!',
                    'impresora' => $cajaFisica->caj_impresora ?? null,
                    'venta_id' => $venta->vta_id,
                    'tipo_impresion' => $cajaFisica->caj_tipo_impresion
                ];
            }); // <-- FIN DE LA TRANSACCIÓN AUTOMÁTICA

            // Devolvemos al navegador el resultado de todo el proceso
            return response()->json($resultadoVenta);

        } catch (\Exception $e) {
            // Laravel ya hizo el rollback en la base de datos automáticamente.
            // Solo devolvemos el error al cajero.
            return response()->json([
                'success' => false, 
                'message' => $e->getMessage()
            ], 500);
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