<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\CuentasCobrar;
use App\Models\DetalleVenta;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\CajaService;
use App\Services\PrecioService;
use App\Services\PresupuestoService;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PdvController extends Controller
{
    public function index(PrecioService $precios)
    {
        // El costo de compra no se envía al navegador del cajero.
        $productos = Producto::where('pro_activo', true)->get()->makeHidden(['pro_preciocosto']);
        $clientes = Cliente::all();

        $activa = Cotizacion::where('cot_activa', true)->first();
        $cotizaciones = [
            'USD' => $activa ? (float) $activa->cot_dolar : 7500,
            'BRL' => $activa ? (float) $activa->cot_real : 1500,
        ];

        // Mismo cálculo que usa el servidor al cobrar (PrecioService).
        $promociones = $precios->promocionesVigentes();

        foreach ($productos as $producto) {
            $precioPromo = $precios->precioPromocional($producto, $precios->promocionDe($producto, $promociones));

            $producto->en_promocion = $precioPromo !== null;
            $producto->precio_promocional = $precioPromo ?? (float) $producto->pro_precioventa;
            $producto->pro_preciomayorista = (float) $producto->pro_preciomayorista;
        }

        // Si viene de un presupuesto (/pdv?presupuesto=ID) se precargan cliente y productos.
        // Los precios se vuelven a calcular con las reglas vigentes; el stock se controla al cobrar.
        $precarga = null;
        if (request()->filled('presupuesto')) {
            $pre = Presupuesto::with('detalles')->find((int) request('presupuesto'));

            try {
                if (! $pre) {
                    throw new NegocioException('El presupuesto que querés vender no existe.');
                }
                app(PresupuestoService::class)->verificarConvertible($pre);

                $precarga = [
                    'id' => $pre->pre_id,
                    'numero' => $pre->numero,
                    'cli_id' => $pre->cli_id,
                    'cliente_nombre' => $pre->nombre_cliente,
                    'items' => $pre->detalles->map(fn ($d) => ['pro_id' => (int) $d->pro_id, 'cantidad' => (float) $d->dpr_cantidad])->values(),
                ];
            } catch (NegocioException $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        return view('pdv.index', compact('productos', 'clientes', 'cotizaciones', 'precarga'));
    }

    public function store(Request $request, PrecioService $precios, CajaService $cajas, StockService $stock, PresupuestoService $presupuestos)
    {
        // 1. VALIDACIÓN. El navegador solo envía QUÉ se vende y CUÁNTO.
        //    Precios, subtotales y total los calcula este servidor.
        $validador = Validator::make($request->all(), [
            'cli_id' => ['required', 'exists:clientes,cli_id'],
            'vta_tipo' => ['required', 'in:CONTADO,CREDITO'],
            'forma_pago' => ['required', 'in:EFECTIVO,TRANSFERENCIA,TARJETA_CREDITO,TARJETA_DEBITO,QR'],
            'nro_transferencia' => ['nullable', 'required_unless:forma_pago,EFECTIVO', 'string', 'max:100'],
            'moneda' => ['required', 'in:GS,USD,BRL'],
            'caj_id' => ['nullable', 'integer'],
            'presupuesto_id' => ['nullable', 'integer'],
            'carrito' => ['required', 'array', 'min:1', 'max:200'],
            'carrito.*.pro_id' => ['required', 'integer', 'exists:productos,pro_id'],
            'carrito.*.cantidad' => ['required', 'numeric', 'gt:0', 'max:100000'],
        ], [
            'cli_id.required' => 'Debe seleccionar un cliente.',
            'cli_id.exists' => 'El cliente seleccionado no existe.',
            'forma_pago.in' => 'La forma de pago no es válida.',
            'nro_transferencia.required_unless' => 'Debe ingresar el código o comprobante del pago.',
            'carrito.required' => 'El carrito está vacío.',
            'carrito.min' => 'El carrito está vacío.',
            'carrito.*.pro_id.exists' => 'Un producto del carrito ya no existe.',
            'carrito.*.cantidad.gt' => 'La cantidad de cada producto debe ser mayor a cero.',
            'carrito.*.cantidad.numeric' => 'La cantidad de cada producto debe ser un número.',
        ]);

        if ($validador->fails()) {
            return response()->json(['success' => false, 'message' => $validador->errors()->first()], 422);
        }

        $datos = $validador->validated();
        $usuario = $request->user();

        // Según el plan del negocio: sin el módulo de varias monedas solo se cobra en guaraníes,
        // y sin el de cobranzas no se vende a crédito.
        if ($datos['moneda'] !== 'GS' && ! \App\Services\ConfiguracionService::modulo('multimoneda')) {
            return response()->json(['success' => false, 'message' => 'Este negocio solo vende en guaraníes.'], 422);
        }
        if ($datos['vta_tipo'] === 'CREDITO' && ! \App\Services\ConfiguracionService::modulo('cobranzas')) {
            return response()->json(['success' => false, 'message' => 'Este negocio no tiene habilitada la venta a crédito.'], 422);
        }

        try {
            // 2. TODO ADENTRO DE UNA TRANSACCIÓN: si algo falla, no queda nada a medias.
            $resultado = DB::transaction(function () use ($datos, $usuario, $precios, $cajas, $stock, $presupuestos) {
                $monedaVenta = $datos['moneda'];

                // A. Sesión de caja abierta del usuario, en la caja de esta terminal
                $consultaSesion = CajaSesion::with(['caja'])
                    ->where('usu_id', $usuario->usu_id)
                    ->where('ses_estado', 'ABIERTA');

                if (! empty($datos['caj_id'])) {
                    $consultaSesion->where('caj_id', $datos['caj_id']);
                }

                $sesionActiva = $consultaSesion->lockForUpdate()->first();

                if (! $sesionActiva) {
                    throw new NegocioException('El equipo está configurado para una caja específica, pero no tiene una sesión ABIERTA en este momento.');
                }

                $cajaFisica = $sesionActiva->caja;
                $sucursalId = $cajaFisica->suc_id ?? 1;

                // B. Cliente (bloqueado para que dos cobros a la vez no superen su límite de crédito)
                $cliente = Cliente::whereKey($datos['cli_id'])->lockForUpdate()->first();

                if ($cliente->cli_bloqueado) {
                    throw new NegocioException('OPERACIÓN DENEGADA: El cliente se encuentra bloqueado por la administración.');
                }

                if ($datos['vta_tipo'] === 'CREDITO' && ! $cliente->cli_permitir_credito) {
                    throw new NegocioException('OPERACIÓN DENEGADA: Este cliente no está habilitado para realizar compras a crédito.');
                }

                // C. Productos: bloqueados, activos y con stock. Si el mismo producto viene en
                //    dos renglones, se suman las cantidades (así no se esquiva el control de stock).
                $cantidades = collect($datos['carrito'])
                    ->groupBy('pro_id')
                    ->map(fn ($renglones) => (float) $renglones->sum('cantidad'));

                $productos = Producto::whereIn('pro_id', $cantidades->keys())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('pro_id');

                $promociones = $precios->promocionesVigentes();
                $esMayorista = (bool) $cliente->cli_es_mayorista;

                $totalGs = 0;
                $lineas = [];

                foreach ($cantidades as $proId => $cantidad) {
                    $producto = $productos->get($proId);

                    if (! $producto || ! $producto->pro_activo) {
                        throw new NegocioException('Uno de los productos del carrito ya no está disponible. Actualizá la pantalla e intentá de nuevo.');
                    }

                    if ($producto->pro_stockactual < $cantidad) {
                        throw new NegocioException("Stock insuficiente de «{$producto->pro_nombre}»: disponible {$producto->pro_stockactual}, pedido {$cantidad}.");
                    }

                    $precio = $precios->precioFinal($producto, $esMayorista, $promociones);
                    $subtotal = round($precio * $cantidad, 2);
                    $totalGs += $subtotal;

                    $lineas[] = [
                        'producto' => $producto,
                        'cantidad' => floor($cantidad) == $cantidad ? (int) $cantidad : $cantidad,
                        'precio' => $precio,
                        'subtotal' => $subtotal,
                    ];
                }

                $totalGs = round($totalGs, 2);

                // D. Límite de crédito (con el total calculado aquí, no el que diga el navegador)
                if ($datos['vta_tipo'] === 'CREDITO') {
                    $deudaActual = (float) CuentasCobrar::where('cli_id', $cliente->cli_id)
                        ->where('cred_estado', 'PENDIENTE')
                        ->sum('cred_saldo_pendiente');
                    $limite = (float) ($cliente->cli_limite_credito ?? 0);

                    // Límite 0 = sin tope (decisión de negocio a confirmar, ver guía)
                    if ($limite > 0 && ($deudaActual + $totalGs) > $limite) {
                        $disponible = max($limite - $deudaActual, 0);

                        throw new NegocioException(
                            "LÍMITE EXCEDIDO: El cliente superó su línea de crédito autorizada.\n\n".
                            'Límite: Gs. '.number_format($limite, 0, ',', '.')."\n".
                            'Crédito Disponible: Gs. '.number_format($disponible, 0, ',', '.')."\n".
                            'Esta Venta: Gs. '.number_format($totalGs, 0, ',', '.')
                        );
                    }
                }

                // E. Numeración fiscal (solo si la caja emite factura), con la sucursal bloqueada
                $sucursal = Sucursal::where('suc_id', $sucursalId)->lockForUpdate()->first();
                $nroFactura = null;
                $timbrado = null;

                if ($cajaFisica->caj_tipo_impresion === 'TICKET_FACTURA') {
                    if (! $sucursal || ! $sucursal->suc_timbrado) {
                        throw new NegocioException('La sucursal no tiene un timbrado cargado. Avisá al administrador.');
                    }

                    // La fecha fiscal es la de Paraguay (el servidor guarda hora UTC)
                    $hoy = now('America/Asuncion')->toDateString();

                    if ($sucursal->suc_timbrado_inicio && $hoy < Carbon::parse($sucursal->suc_timbrado_inicio)->toDateString()) {
                        throw new NegocioException('El timbrado de la sucursal todavía no está vigente.');
                    }
                    if ($sucursal->suc_timbrado_fin && $hoy > Carbon::parse($sucursal->suc_timbrado_fin)->toDateString()) {
                        throw new NegocioException('El timbrado de la sucursal está VENCIDO. No se pueden emitir facturas hasta cargar el nuevo.');
                    }

                    $timbrado = $sucursal->suc_timbrado;
                    $nroFactura = str_pad($sucursal->suc_factura_secuencia, 7, '0', STR_PAD_LEFT);
                    $sucursal->increment('suc_factura_secuencia');
                }

                // F. Venta y detalle
                $venta = Venta::create([
                    'suc_id' => $sucursalId,
                    'cli_id' => $cliente->cli_id,
                    'usu_id' => $usuario->usu_id,
                    'ses_id' => $sesionActiva->ses_id,
                    'vta_tipo' => $datos['vta_tipo'],
                    'vta_formapago' => $datos['forma_pago'],
                    'nro_transferencia' => $datos['forma_pago'] === 'EFECTIVO' ? null : ($datos['nro_transferencia'] ?? null),
                    'vta_moneda' => $monedaVenta,
                    'vta_total' => $totalGs,
                    'vta_timbrado' => $timbrado,
                    'vta_nro_factura' => $nroFactura,
                    'vta_estado' => 'CONFIRMADA',
                ]);

                $totalExenta = 0;
                $totalIva5 = 0;
                $totalIva10 = 0;

                foreach ($lineas as $linea) {
                    /** @var Producto $producto */
                    $producto = $linea['producto'];

                    DetalleVenta::create([
                        'vta_id' => $venta->vta_id,
                        'pro_id' => $producto->pro_id,
                        'det_cantidad' => $linea['cantidad'],
                        'det_preciounitario' => $linea['precio'],
                        'det_subtotal' => $linea['subtotal'],
                        'det_preciocosto' => $producto->pro_preciocosto ?? 0, // costo histórico
                    ]);

                    $stock->mover((int) $producto->pro_id, 'VENTA', -1 * (float) $linea['cantidad'], 'Venta en punto de venta', 'venta:'.$venta->vta_id);

                    // IVA incluido en el precio
                    if ((int) $producto->pro_tipo_iva === 10) {
                        $totalIva10 += $linea['subtotal'] / 11;
                    } elseif ((int) $producto->pro_tipo_iva === 5) {
                        $totalIva5 += $linea['subtotal'] / 21;
                    } else {
                        $totalExenta += $linea['subtotal'];
                    }
                }

                $venta->update([
                    'vta_total_exenta' => round($totalExenta, 2),
                    'vta_total_iva5' => round($totalIva5, 2),
                    'vta_total_iva10' => round($totalIva10, 2),
                ]);

                // F2. Si la venta viene de un presupuesto, queda facturado en la misma transacción.
                if (! empty($datos['presupuesto_id'])) {
                    $presupuestos->marcarFacturado((int) $datos['presupuesto_id'], $venta->vta_id);
                }

                // G. Ingreso a caja (solo contado), convertido a la moneda en que se cobró.
                //    Queda en el libro con su forma de pago, pero SOLO el efectivo suma al saldo físico.
                if ($datos['vta_tipo'] === 'CONTADO') {
                    $montoIngresoCaja = $totalGs;

                    if ($monedaVenta !== 'GS') {
                        $cotizacion = Cotizacion::where('cot_activa', true)->first();
                        $tasa = $monedaVenta === 'USD'
                            ? ($cotizacion->cot_dolar ?? 7500)
                            : ($cotizacion->cot_real ?? 1500);
                        $montoIngresoCaja = round($totalGs / $tasa, 2);
                    }

                    $cajas->registrar(
                        $sesionActiva, 'INGRESO', $montoIngresoCaja, $monedaVenta,
                        'Venta Nro. '.($nroFactura ?? $venta->vta_id).' ('.$datos['forma_pago'].')',
                        $datos['forma_pago'], ['vta_id' => $venta->vta_id]
                    );
                }

                // H. A crédito: cuenta por cobrar (la deuda es siempre en guaraníes)
                if ($datos['vta_tipo'] === 'CREDITO') {
                    CuentasCobrar::create([
                        'vta_id' => $venta->vta_id,
                        'cli_id' => $cliente->cli_id,
                        'cred_monto_total' => $totalGs,
                        'cred_saldo_pendiente' => $totalGs,
                        'cred_fecha_vencimiento' => now()->addDays(30),
                        'cred_estado' => 'PENDIENTE',
                    ]);
                }

                return [
                    'success' => true,
                    'message' => '¡Venta registrada con éxito, stock y correlativo actualizados!',
                    'impresora' => $cajaFisica->caj_impresora ?? null,
                    'venta_id' => $venta->vta_id,
                    'tipo_impresion' => $cajaFisica->caj_tipo_impresion,
                    'total_gs' => $totalGs,
                ];
            });

            return response()->json($resultado);

        } catch (NegocioException $e) {
            // Error esperado: se le explica al cajero.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            // Error inesperado: el detalle técnico va al log, al cajero solo un código.
            $codigo = strtoupper(Str::random(6));
            Log::error("Error al registrar venta [{$codigo}]", [
                'usuario' => $usuario->usu_id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => "No se pudo registrar la venta. Avisá al administrador (código {$codigo}).",
            ], 500);
        }
    }

    public function storeClienteAjax(Request $request)
    {
        $request->validate([
            'cli_ruc_ci' => 'required|string|max:30|unique:clientes,cli_ruc_ci',
            'cli_nombre' => 'required|string|max:100',
            'cli_apellido' => 'required|string|max:100',
            'cli_telefono' => 'required|string|max:30',
            'cli_email' => 'required|email|max:100',
            'cli_direccion' => 'required|string',
            'cli_limite_credito' => 'nullable|numeric|min:0',
        ]);

        // Solo se copian los campos de contacto. Mayorista y límite de crédito cambian
        // el precio y la deuda permitida: solo los puede fijar quien tiene CLIENTES_CREDITO.
        $datos = $request->only(['cli_ruc_ci', 'cli_nombre', 'cli_apellido', 'cli_telefono', 'cli_email', 'cli_direccion']);

        $puedeCredito = $request->user()->tienePermiso('CLIENTES_CREDITO');
        $datos['cli_es_mayorista'] = $puedeCredito ? $request->boolean('cli_es_mayorista') : false;
        $datos['cli_limite_credito'] = $puedeCredito ? ($request->input('cli_limite_credito') ?: 0) : 0;
        $datos['cli_permitir_credito'] = false;
        $datos['cli_bloqueado'] = false;

        $cliente = Cliente::create($datos);

        return response()->json(['success' => true, 'cliente' => $cliente]);
    }

    public function ticketSimple(Request $request, $id)
    {
        $venta = $this->ventaVisible($request, $id);

        return view('pdv.ticket-simple', compact('venta'));
    }

    public function ticketFactura(Request $request, $id)
    {
        $venta = $this->ventaVisible($request, $id);

        return view('pdv.ticket-factura', compact('venta'));
    }

    /**
     * Un cajero solo ve los tickets de sus propias ventas (antes cualquiera con acceso al POS
     * podía abrir el ticket de cualquier venta cambiando el número en la dirección).
     * Quien tiene acceso al historial de ventas (y el Administrador) los ve todos.
     */
    private function ventaVisible(Request $request, $id): Venta
    {
        $venta = Venta::with(['detalles.producto', 'cliente', 'sesion.caja', 'sucursal'])->findOrFail($id);

        abort_unless(
            (int) $venta->usu_id === (int) $request->user()->usu_id || $request->user()->can('VENTAS_HISTORIAL'),
            403,
            'No tenés permiso para ver este ticket.'
        );

        return $venta;
    }
}
