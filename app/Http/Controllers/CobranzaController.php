<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CuentasCobrar;
use App\Models\Cobranza;
use App\Models\DetalleCobranza;
use App\Models\CajaSesion;
use App\Models\CajaMovimiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CobranzaController extends Controller
{
        public function index()
            {
                // Traemos las cuentas con saldo pendiente, el cliente, y el historial de cobros realizados
                $cuentas = CuentasCobrar::with([
                    'cliente', 
                   'detallesCobranza.cobranza.sesion.caja.sucursal' // Pasamos por la sesión primero // Traemos el detalle, el cobro cabecera, la caja y la sucursal
                ])
                ->where('cred_estado', 'PENDIENTE')
                ->orderBy('cred_fecha_vencimiento', 'asc')
                ->get();

                return view('cobranzas.index', compact('cuentas'));
            }

    public function store(Request $request)
    {
        $request->validate([
            'cred_id' => 'required|exists:cuentas_cobrar,cred_id',
            'monto_pagar' => 'required|numeric|min:1',
            'observacion' => 'nullable|string',
            'caj_id' => 'nullable'
        ]);

        try {
    
            DB::beginTransaction();
            $usuario = Auth::user();
            $cuenta = CuentasCobrar::findOrFail($request->cred_id);

            // 1. VALIDACIÓN: Que no pague más del saldo pendiente
            if ($request->monto_pagar > $cuenta->cred_saldo_pendiente) {
                return response()->json(['success' => false, 'message' => 'El monto supera la deuda actual.']);
            }

            // 2. Capturamos la caja del navegador
            $cajIdEquipo = $request->input('caj_id'); 

            // 3. Armamos la búsqueda base de la sesión
            $query = CajaSesion::with('caja')
                             ->where('usu_id', $usuario->usu_id ?? 1)
                             ->where('ses_estado', 'ABIERTA');

            // 4. Obligamos a buscar solo en la caja configurada en esta computadora
            if (!empty($cajIdEquipo)) {
                $query->where('caj_id', $cajIdEquipo);
            }

            $sesionActiva = $query->first();

            // 5. Bloqueo de seguridad si la caja correcta no está abierta
            if (!$sesionActiva) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Esta computadora está configurada para una caja, pero no tienes un turno ABIERTO en ella.'
                ], 422);
            }

            // 3. Crear Cabecera de Cobranza (Guarda la fecha automáticamente)[cite: 17]
            $cobranza = Cobranza::create([
                'suc_id' => $sesionActiva->caja->suc_id,
                'cli_id' => $cuenta->cli_id,
                'usu_id' => $usuario->usu_id ?? 1,
                'ses_id' => $sesionActiva->ses_id,
                'cob_fecha' => now(), // Guarda la fecha y hora actual
                'cob_monto_total' => $request->monto_pagar,
                'cob_estado' => 'ACTIVA'
            ]);

            // 4. Crear Detalle de Cobranza[cite: 17]
            DetalleCobranza::create([
                'cob_id' => $cobranza->cob_id,
                'cred_id' => $cuenta->cred_id,
                'det_monto_pagado' => $request->monto_pagar
            ]);

            // 5. Restar la deuda y actualizar estado[cite: 17]
            $cuenta->cred_saldo_pendiente -= $request->monto_pagar;
            if ($cuenta->cred_saldo_pendiente <= 0) {
                $cuenta->cred_estado = 'PAGADA';
            }
            $cuenta->save();

            // 6. Impactar el ingreso en la Caja y Movimientos[cite: 18]
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

            $cajaFisica = $sesionActiva->caja;
            if (isset($cajaFisica->caj_saldo_gs)) {
                $cajaFisica->increment('caj_saldo_gs', $request->monto_pagar);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Cobro registrado exitosamente y sumado a la caja.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
     }
    

            public function imprimirTicket($id)
            {
                // Traemos el cobro cabecera con sus detalles y cliente
                $cobro = Cobranza::with(['detalles.cuenta', 'cliente', 'usuario'])->findOrFail($id);
                return view('cobranzas.ticket', compact('cobro'));
            }

}