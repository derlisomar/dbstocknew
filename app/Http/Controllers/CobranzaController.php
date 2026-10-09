<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Models\Cobranza;
use App\Models\CuentasCobrar;
use App\Models\DetalleCobranza;
use App\Services\AuditoriaService;
use App\Services\CajaService;
use App\Services\CobroService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CobranzaController extends Controller
{
    public function index()
    {
        // Traemos las cuentas con saldo pendiente, el cliente, y el historial de cobros realizados
        $cuentas = CuentasCobrar::with([
                        'cliente',
                        // Los cobros anulados ya no cuentan en el historial de la cuenta.
                        'detallesCobranza' => fn ($q) => $q->whereHas('cobranza', fn ($c) => $c->where('cob_estado', 'ACTIVA')),
                        'detallesCobranza.cobranza.sesion.caja.sucursal'
                    ])
                    ->where('cred_estado', 'PENDIENTE')
                    ->orderBy('cred_fecha_vencimiento', 'asc')
                    ->get();

        return view('cobranzas.index', compact('cuentas'));
    }

    public function store(Request $request, CajaService $cajas)
    {
        // 1. Validaciones del formulario
        $request->validate([
            'cred_id' => 'required|exists:cuentas_cobrar,cred_id',
            'monto_pagar' => 'required|numeric|min:1',
            'forma_pago' => 'nullable|in:EFECTIVO,TRANSFERENCIA,TARJETA_CREDITO,TARJETA_DEBITO,QR',
            'observacion' => 'nullable|string|max:200',
            'caj_id' => 'nullable|integer', // ID de la terminal física
        ]);

        $usuario = $request->user();
        $formaPago = $request->input('forma_pago', 'EFECTIVO');

        try {
            // Todo en una transacción: si algo falla, no queda nada a medias.
            $resultado = DB::transaction(function () use ($request, $cajas, $usuario, $formaPago) {
                $monto = round((float) $request->monto_pagar, 2);

                // 2. Bloqueo de la deuda: dos personas cobrando a la vez no pueden dejar el saldo en negativo.
                $cuenta = CuentasCobrar::where('cred_id', $request->cred_id)->lockForUpdate()->first();

                if (! $cuenta || $cuenta->cred_estado !== 'PENDIENTE') {
                    throw new NegocioException('Esta cuenta ya no tiene deuda pendiente.');
                }

                if ($monto > round((float) $cuenta->cred_saldo_pendiente, 2)) {
                    throw new NegocioException('El monto supera la deuda actual.');
                }

                // 3. Turno ABIERTO del cajero, en la caja de este equipo
                $sesion = $cajas->sesionAbiertaDe($usuario, $request->filled('caj_id') ? (int) $request->caj_id : null);

                if (! $sesion) {
                    throw new NegocioException('Esta computadora está configurada para una caja, pero no tenés un turno ABIERTO en ella.');
                }

                // 4. Cabecera y detalle del cobro
                $cobranza = Cobranza::create([
                    'suc_id' => $sesion->caja->suc_id,
                    'cli_id' => $cuenta->cli_id,
                    'usu_id' => $usuario->usu_id,
                    'ses_id' => $sesion->ses_id,
                    'cob_fecha' => now(),
                    'cob_monto_total' => $monto,
                    'cob_estado' => 'ACTIVA',
                    'cob_formapago' => $formaPago,
                ]);

                DetalleCobranza::create([
                    'cob_id' => $cobranza->cob_id,
                    'cred_id' => $cuenta->cred_id,
                    'det_monto_pagado' => $monto,
                ]);

                // 5. Se descuenta la deuda
                $saldo = round((float) $cuenta->cred_saldo_pendiente - $monto, 2);
                $cuenta->update([
                    'cred_saldo_pendiente' => $saldo,
                    'cred_estado' => $saldo <= 0 ? 'PAGADA' : 'PENDIENTE',
                ]);

                // 6. Ingreso en el libro de caja; solo el efectivo suma al saldo físico
                $concepto = 'Cobro a Crédito - Venta Nro '.$cuenta->vta_id.' ('.$formaPago.')';
                if ($request->filled('observacion')) {
                    $concepto .= ' | Obs: '.$request->observacion;
                }

                $cajas->registrar($sesion, 'INGRESO', $monto, 'GS', $concepto, $formaPago, ['cob_id' => $cobranza->cob_id]);

                AuditoriaService::registrar('COBRANZA', 'cobranzas', $cobranza->cob_id, [
                    'cuenta' => $cuenta->cred_id,
                    'monto' => $monto,
                    'forma' => $formaPago,
                    'saldo_restante' => $saldo,
                ]);

                return ['success' => true, 'message' => 'Cobro registrado exitosamente.', 'cob_id' => $cobranza->cob_id];
            });

            return response()->json($resultado);

        } catch (NegocioException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            // El detalle técnico va al log; al cajero, solo un código.
            $codigo = strtoupper(Str::random(6));
            Log::error("Error al registrar cobranza [{$codigo}]", ['usuario' => $usuario->usu_id, 'exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => "No se pudo registrar el cobro. Avisá al administrador (código {$codigo}).",
            ], 500);
        }
    }

    /** Lista de cobros realizados (vigentes y anulados), con el botón para anular. */
    public function historial(Request $request)
    {
        $consulta = Cobranza::with(['cliente', 'usuario', 'sesion.caja'])->orderByDesc('cob_fecha')->orderByDesc('cob_id');

        if ($request->filled('desde')) {
            $consulta->whereDate('cob_fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $consulta->whereDate('cob_fecha', '<=', $request->hasta);
        }
        if (in_array($request->estado, ['ACTIVA', 'ANULADA'], true)) {
            $consulta->where('cob_estado', $request->estado);
        }

        $cobros = $consulta->paginate(20)->withQueryString();

        return view('cobranzas.historial', compact('cobros'));
    }

    public function anular(Request $request, $id, CobroService $cobros)
    {
        $request->validate(['motivo' => 'required|string|max:255']);

        try {
            $cobros->anular((int) $id, $request->user(), $request->input('motivo'));

            return back()->with('success', 'Cobro anulado. La deuda volvió a la cuenta del cliente.');

        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());

        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("Error al anular cobro [{$codigo}]", ['cob_id' => $id, 'exception' => $e]);

            return back()->with('error', "No se pudo anular el cobro. Avisá al administrador (código {$codigo}).");
        }
    }

    public function imprimirTicket(Request $request, $id)
    {
        $cobro = Cobranza::with(['detalles.cuenta', 'cliente', 'usuario', 'sesion.caja.sucursal'])->findOrFail($id);

        // Un cajero solo imprime sus propios cobros; los demás los ve quien tiene acceso al historial de ventas.
        abort_unless(
            (int) $cobro->usu_id === (int) $request->user()->usu_id || $request->user()->can('VENTAS_HISTORIAL'),
            403,
            'No tenés permiso para ver este ticket.'
        );

        return view('cobranzas.ticket', compact('cobro'));
    }
}
