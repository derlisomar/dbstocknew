<?php

namespace App\Http\Controllers\Vendedor;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\LicenciaPago;
use App\Models\Plan;
use App\Models\PlanAdicional;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService as Cfg;
use App\Services\LicenciaService;
use Illuminate\Http\Request;

class LicenciaController extends Controller
{
    // ------------------------------------------------------------------ planes

    public function planes(Request $request)
    {
        return view('vendedor.planes', [
            'planes' => Plan::orderBy('plan_orden')->orderBy('plan_id')->get(),
            'adicionales' => PlanAdicional::orderBy('ada_orden')->orderBy('ada_id')->get(),
            'titulo' => Cfg::get('pub_planes_titulo'),
            'texto' => Cfg::get('pub_planes_texto'),
            'editar' => $request->filled('editar') ? Plan::find((int) $request->query('editar')) : null,
        ]);
    }

    public function guardarPlan(Request $request, $id = null)
    {
        $d = $request->validate([
            'plan_nombre' => ['required', 'string', 'max:80'],
            'plan_descripcion' => ['nullable', 'string', 'max:255'],
            'plan_edicion' => ['required', 'in:'.implode(',', array_keys(config('modulos.ediciones')))],
            'plan_modulos' => ['nullable', 'array'],
            'plan_modulos.*' => ['string'],
            'plan_precio' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'plan_meses' => ['required', 'integer', 'min:0', 'max:60'],
            'plan_max_sucursales' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'plan_max_cajas' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'plan_max_usuarios' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'plan_orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'plan_etiqueta' => ['nullable', 'string', 'max:30'],
            'plan_boton' => ['nullable', 'string', 'max:40'],
            'plan_caracteristicas' => ['nullable', 'string', 'max:3000'],
        ], ['plan_nombre.required' => 'Escribí el nombre del plan.']);

        $extras = array_values(array_intersect($d['plan_modulos'] ?? [], array_keys(config('modulos.catalogo'))));
        $datos = [
            'plan_nombre' => $d['plan_nombre'],
            'plan_descripcion' => $d['plan_descripcion'] ?? null,
            'plan_edicion' => $d['plan_edicion'],
            'plan_modulos' => $extras ? json_encode($extras) : null,
            'plan_precio' => $d['plan_precio'],
            'plan_meses' => $d['plan_meses'],
            'plan_max_sucursales' => (int) ($d['plan_max_sucursales'] ?? 0),
            'plan_max_cajas' => (int) ($d['plan_max_cajas'] ?? 0),
            'plan_max_usuarios' => (int) ($d['plan_max_usuarios'] ?? 0),
            'plan_publico' => $request->boolean('plan_publico'),
            'plan_destacado' => $request->boolean('plan_destacado'),
            'plan_orden' => (int) ($d['plan_orden'] ?? 0),
            'plan_etiqueta' => filled($d['plan_etiqueta'] ?? null) ? trim($d['plan_etiqueta']) : null,
            'plan_boton' => filled($d['plan_boton'] ?? null) ? trim($d['plan_boton']) : null,
            'plan_caracteristicas' => filled($d['plan_caracteristicas'] ?? null) ? trim($d['plan_caracteristicas']) : null,
        ];

        if ($id) {
            $plan = Plan::findOrFail($id);
            $plan->update($datos);
        } else {
            $plan = Plan::create($datos + ['plan_activo' => true]);
        }

        AuditoriaService::registrar('VENDEDOR_PLAN', 'planes', $plan->plan_id, ['por' => 'vendedor', 'plan' => $plan->plan_nombre]);

        return redirect()->route('vendedor.planes')->with('success', $id
            ? 'Plan guardado. Los cambios se aplican al negocio cuando le asignes el plan de nuevo.'
            : 'Plan creado.');
    }

    public function estadoPlan($id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['plan_activo' => ! $plan->plan_activo]);

        return back()->with('success', 'Plan '.($plan->plan_activo ? 'activado' : 'desactivado').'.');
    }

    /** Guarda los adicionales («capacidad flexible») y los textos de la sección de precios. */
    public function guardarAdicionales(Request $request)
    {
        $d = $request->validate([
            'pub_planes_titulo' => ['nullable', 'string', 'max:140'],
            'pub_planes_texto' => ['nullable', 'string', 'max:300'],
            'ad' => ['nullable', 'array', 'max:30'],
            'ad.*.nombre' => ['nullable', 'string', 'max:80'],
            'ad.*.precio' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'ad.*.periodo' => ['nullable', 'string', 'max:20'],
            'ad.*.orden' => ['nullable', 'integer', 'min:0', 'max:999'],
            'ad.*.activo' => ['nullable', 'boolean'],
            'ad.*.eliminar' => ['nullable', 'boolean'],
            'nuevo.nombre' => ['nullable', 'string', 'max:80'],
            'nuevo.precio' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'nuevo.periodo' => ['nullable', 'string', 'max:20'],
        ]);

        Cfg::set([
            'pub_planes_titulo' => trim((string) ($d['pub_planes_titulo'] ?? '')) ?: null,
            'pub_planes_texto' => trim((string) ($d['pub_planes_texto'] ?? '')) ?: null,
        ]);

        foreach (($d['ad'] ?? []) as $id => $fila) {
            $ada = PlanAdicional::find((int) $id);
            if (! $ada) {
                continue;
            }
            if (! empty($fila['eliminar'])) {
                $ada->delete();

                continue;
            }
            $ada->update([
                'ada_nombre' => trim((string) ($fila['nombre'] ?? '')) ?: $ada->ada_nombre,
                'ada_precio' => (float) ($fila['precio'] ?? 0),
                'ada_periodo' => trim((string) ($fila['periodo'] ?? '')) ?: 'mes',
                'ada_orden' => (int) ($fila['orden'] ?? 0),
                'ada_activo' => ! empty($fila['activo']),
            ]);
        }

        $nuevo = $d['nuevo'] ?? [];
        if (filled($nuevo['nombre'] ?? null)) {
            PlanAdicional::create([
                'ada_nombre' => trim($nuevo['nombre']),
                'ada_precio' => (float) ($nuevo['precio'] ?? 0),
                'ada_periodo' => trim((string) ($nuevo['periodo'] ?? '')) ?: 'mes',
                'ada_orden' => (int) (PlanAdicional::max('ada_orden') ?? 0) + 1,
                'ada_activo' => true,
            ]);
        }

        AuditoriaService::registrar('VENDEDOR_PLAN', 'plan_adicionales', 0, ['por' => 'vendedor', 'accion' => 'adicionales y textos']);

        return redirect()->route('vendedor.planes')->with('success', 'Adicionales y textos guardados.');
    }

    // ------------------------------------------------------------------ licencia y pagos

    public function licencia()
    {
        $planId = (int) Cfg::get('lic_plan_id', 0);

        return view('vendedor.licencia', [
            'estado' => LicenciaService::estado(),
            'plan' => $planId ? Plan::find($planId) : null,
            'planes' => Plan::where('plan_activo', true)->orderBy('plan_id')->get(),
            'pagos' => LicenciaPago::with('plan')->orderByDesc('lpa_id')->limit(50)->get(),
            'ultimoActivo' => LicenciaPago::where('lpa_estado', 'ACTIVO')->max('lpa_id'),
            'suspendida' => Cfg::get('lic_suspendida') === '1',
            'graciaDias' => (int) Cfg::get('lic_gracia_dias', config('vendedor.gracia_dias', 5)),
            'aviso' => $this->aviso(),
        ]);
    }

    /** @return array{texto:string,email:?string,wa:?string} */
    private function aviso(): array
    {
        $texto = ExtrasController::textoAviso();
        $tel = preg_replace('/\D+/', '', (string) Cfg::get('negocio_telefono'));
        if ($tel !== '' && str_starts_with($tel, '0')) {
            $tel = '595'.substr($tel, 1);       // 0981... -> 595981...
        }

        return [
            'texto' => $texto,
            'email' => Cfg::get('negocio_email') ?: null,
            'wa' => $tel !== '' ? 'https://wa.me/'.$tel.'?text='.rawurlencode($texto) : null,
        ];
    }

    public function aplicarPlan(Request $request)
    {
        $d = $request->validate(['plan_id' => ['required', 'integer', 'exists:planes,plan_id']], ['plan_id.required' => 'Elegí un plan.']);

        try {
            $plan = LicenciaService::aplicarPlan((int) $d['plan_id']);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Plan «{$plan->plan_nombre}» asignado: se aplicaron la edición, los módulos y los límites.");
    }

    public function ajustes(Request $request)
    {
        $d = $request->validate([
            'lic_tipo' => ['required', 'in:SUSCRIPCION,PERPETUA'],
            'lic_vence' => ['nullable', 'date'],
            'lic_gracia_dias' => ['required', 'integer', 'min:0', 'max:60'],
            'lic_suspendida' => ['nullable', 'boolean'],
        ]);

        Cfg::set([
            'lic_tipo' => $d['lic_tipo'],
            'lic_vence' => $d['lic_tipo'] === 'PERPETUA' ? null : ($d['lic_vence'] ?? null),
            'lic_gracia_dias' => (string) $d['lic_gracia_dias'],
            'lic_suspendida' => $request->boolean('lic_suspendida') ? '1' : null,
        ]);
        AuditoriaService::registrar('VENDEDOR_LICENCIA', 'configuracion_sistema', null, ['por' => 'vendedor'] + $d);

        return back()->with('success', 'Ajustes de licencia guardados.');
    }

    public function registrarPago(Request $request)
    {
        $d = $request->validate([
            'fecha' => ['nullable', 'date'],
            'monto' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'forma' => ['nullable', 'string', 'max:30'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'plan_id' => ['nullable', 'integer', 'exists:planes,plan_id'],
            'meses' => ['nullable', 'integer', 'min:0', 'max:60'],
            'hasta' => ['nullable', 'date'],
            'nota' => ['nullable', 'string', 'max:255'],
        ], ['monto.required' => 'Indicá el monto del pago.']);

        try {
            $pago = LicenciaService::registrarPago($d);
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $msg = $pago->lpa_hasta
            ? 'Pago registrado: el plan queda vigente hasta el '.$pago->lpa_hasta->format('d/m/Y').'.'
            : 'Pago único registrado: la licencia queda permanente.';

        return back()->with('success', $msg);
    }

    public function anularPago(Request $request, $id)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'max:200']], ['motivo.required' => 'Indicá el motivo.']);

        try {
            LicenciaService::anularPago((int) $id, $d['motivo']);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pago anulado: el vencimiento volvió a como estaba.');
    }
}
