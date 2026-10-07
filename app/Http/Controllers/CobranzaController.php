<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CuentasCobrar;
use App\Models\Cobranza;
use App\Models\DetalleCobranza;
use App\Models\CajaSesion;
use App\Models\CajaMovimiento;
use App\Models\Caja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CobranzaController extends Controller
{
    public function index()
    {
        // Traemos las cuentas con saldo pendiente, el cliente, y el historial de cobros realizados
        $cuentas = CuentasCobrar::with([
                        'cliente',
                        'detallesCobranza.cobranza.sesion.caja.sucursal'
                    ])
                    ->where('cred_estado', 'PENDIENTE')
                    ->orderBy('cred_fecha_vencimiento', 'asc')
                    ->get();

        return view('cobranzas.index', compact('cuentas'));
    }

    public function store(Request $request)
    {
        // 1. Validaciones del formulario
        $request->validate([
            'cred_id' => 'required|exists:cuentas_cobrar,cred_id',
            'monto_pagar' => 'required|numeric|min:1',
            'observacion' => 'nullable|string',
            'caj_id' => 'nullable' // ID de la terminal física
        ]);

        try {
            // Usamos DB::transaction() para blindar toda la operación. Rollback automático en caso de fallo.
            return DB::transaction(function () use ($request) {
                
                $usuario = Auth::user();
                $cajIdEquipo = $request->input('caj_id');

                // 2. BLOQUEO DE CONCURRENCIA SOBRE LA DEUDA:
                // Previene que dos personas cobren simultáneamente y el saldo quede en negativo.
                $cuenta = CuentasCobrar::where('cred_id', $request->cred_id)->lockForUpdate()->first();

                if (!$cuenta) {
                    throw new \Exception('No se encontró la cuenta por cobrar.');
                }

                if ($request->monto_pagar > $cuenta->cred_saldo_pendiente) {
                    throw new \Exception('El monto supera la deuda actual.');
                }

                // 3. BUSCAR SESIÓN VINCULADA ESTRICTAMENTE A LA CAJA DEL EQUIPO
                $query = CajaSesion::with('caja')
                                 ->where('usu_id', $usuario->usu_id ?? 1)
                                 ->where('ses_estado', 'ABIERTA');

                if (!empty($cajIdEquipo)) {
                    $query->where('caj_id', $cajIdEquipo);
                }

                // Bloqueamos también la sesión para evitar problemas de sincronización al actualizar saldos
                $sesionActiva = $query->lockForUpdate()->first();

                if (!$sesionActiva) {
                    throw new \Exception('Esta computadora está configurada para una caja, pero no tienes un turno ABIERTO en ella.');
                }

                // 4. CREAR COBRANZA CABECERA
                $cobranza = Cobranza::create([
                    'suc_id' => $sesionActiva->caja->suc_id,
                    'cli_id' => $cuenta->cli_id,
                    'usu_id' => $usuario->usu_id ?? 1,
                    'ses_id' => $sesionActiva->ses_id,
                    'cob_fecha' => now(),
                    'cob_monto_total' => $request->monto_pagar,
                    'cob_estado' => 'ACTIVA'
                ]);

                // 5. CREAR DETALLE DE COBRANZA
                DetalleCobranza::create([
                    'cob_id' => $cobranza->cob_id,
                    'cred_id' => $cuenta->cred_id,
                    'det_monto_pagado' => $request->monto_pagar
                ]);

                // 6. RESTAR LA DEUDA Y ACTUALIZAR ESTADO
                $cuenta->cred_saldo_pendiente -= $request->monto_pagar;
                if ($cuenta->cred_saldo_pendiente <= 0) {
                    $cuenta->cred_estado = 'PAGADA';
                }
                $cuenta->save();

                // 7. REGISTRAR EL INGRESO FÍSICO EN LA CAJA Y SUMAR AL SALDO CONSOLIDADO
                $concepto = 'Cobro a Crédito - Venta Nro ' . $cuenta->vta_id;
                if ($request->observacion) {
                    $concepto .= ' | Obs: ' . $request->observacion;
                }

                CajaMovimiento::create([
                    'ses_id' => $sesionActiva->ses_id,
                    'mov_tipo' => 'INGRESO',
                    'mov_monto' => $request->monto_pagar,
                    'mov_concepto' => $concepto,
                    'mov_moneda' => 'GS' 
                ]);

                // Actualizamos directamente la tabla Caja (que bloqueamos indirectamente vía la sesión)
                $cajaFisica = Caja::where('caj_id', $sesionActiva->caj_id)->lockForUpdate()->first();
                if ($cajaFisica && isset($cajaFisica->caj_saldo_gs)) {
                    $cajaFisica->increment('caj_saldo_gs', $request->monto_pagar);
                }

                return response()->json([
                    'success' => true, 
                    'message' => 'Cobro registrado exitosamente y sumado a la caja correcta.'
                ]);
            });

        } catch (\Exception $e) {
            // Ya no es necesario DB::rollBack() porque DB::transaction lo maneja por nosotros
            return response()->json([
                'success' => false, 
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function detallesCobranza()
    {
        return $this->hasMany(DetalleCobranza::class, 'cred_id', 'cred_id');
    }

    public function cobranza()
    {
        return $this->belongsTo(Cobranza::class, 'cob_id', 'cob_id');
    }

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caj_id', 'caj_id');
    }

    public function imprimirTicket($id)
    {
        // Traemos el cobro cabecera con sus detalles y cliente
        $cobro = Cobranza::with(['detalles.cuenta', 'cliente', 'usuario'])->findOrFail($id);
        
        return view('cobranzas.ticket', compact('cobro'));
    }
}