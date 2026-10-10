<?php

namespace App\Http\Controllers;

use App\Exceptions\NegocioException;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService;
use App\Services\ContabilidadInformes;
use App\Services\ContabilidadMotor;
use App\Services\ContabilidadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContabilidadController extends Controller
{
    public function __construct(
        private ContabilidadService $c,
        private ContabilidadMotor $motor,
        private ContabilidadInformes $inf
    ) {
    }

    /** Si la contabilidad todavía no se preparó, solo se muestra el panel de arranque. */
    private function exigirPreparada()
    {
        return $this->c->preparada() ? null : redirect()->route('contabilidad.index')->with('error', 'Primero preparé la contabilidad desde este panel.');
    }

    /** Pone al día los asientos antes de mostrar un informe (si falla, se informa pero no se rompe la pantalla). */
    private function alDia(): array
    {
        try {
            return $this->motor->sincronizar();
        } catch (\Throwable $e) {
            Log::warning('Contabilidad: falló la sincronización: '.$e->getMessage());

            return ['generados' => 0, 'errores' => ['No se pudo actualizar la contabilidad: '.$e->getMessage()]];
        }
    }

    /** @return array{0:string,1:string} */
    private function rango(Request $r, ?string $desdeDefecto = null): array
    {
        $r->validate(['desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date']]);
        $hasta = $r->filled('hasta') ? Carbon::parse($r->hasta) : now();
        $desde = $r->filled('desde') ? Carbon::parse($r->desde) : ($desdeDefecto ? Carbon::parse($desdeDefecto) : now()->startOfMonth());
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [$desde->toDateString(), $hasta->toDateString()];
    }

    private function csv(string $nombre, array $cabecera, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($cabecera, $filas) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF"); // para que Excel lea bien las tildes
            fputcsv($f, $cabecera, ';');
            foreach ($filas as $fila) {
                fputcsv($f, $fila, ';');
            }
            fclose($f);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ panel

    public function index(Request $request)
    {
        $preparada = $this->c->preparada();
        $sync = ['generados' => 0, 'errores' => []];
        $datos = [];

        if ($preparada) {
            $sync = $this->alDia();
            $hoy = now()->toDateString();
            $inicioMes = now()->startOfMonth()->toDateString();

            $saldos = [];
            foreach (['caja_gs', 'caja_usd', 'caja_brl', 'banco', 'clientes', 'proveedores', 'mercaderias', 'iva_debito', 'iva_credito'] as $clave) {
                $cue = DB::table('cont_cuentas')->where('cue_clave', $clave)->first();
                if (! $cue) {
                    continue;
                }
                $s = DB::table('cont_lineas as l')->join('cont_asientos as a', 'a.asi_id', '=', 'l.asi_id')
                    ->where('l.cue_id', $cue->cue_id)->selectRaw('COALESCE(SUM(l.lin_debe),0) d, COALESCE(SUM(l.lin_haber),0) h')->first();
                $saldos[$clave] = [
                    'nombre' => $cue->cue_nombre, 'id' => $cue->cue_id,
                    'saldo' => round(ContabilidadService::naturalezaDeudora($cue->cue_tipo) ? $s->d - $s->h : $s->h - $s->d, 2),
                ];
            }

            $datos = [
                'saldos' => $saldos,
                'resultadoMes' => $this->inf->resultados($inicioMes, $hoy),
                'asientos' => DB::table('cont_asientos')->count(),
                'pendientes' => $this->motor->pendientes(),
                'inventarioSistema' => $this->inf->valorInventario(),
                'ultimaSync' => ConfiguracionService::get('cont_ultima_sync'),
                'inicio' => $this->c->inicio()?->format('d/m/Y'),
                'cerradoHasta' => $this->c->cerradoHasta()?->format('d/m/Y'),
                'cerradoIso' => $this->c->cerradoHasta()?->toDateString(),
                'puedeGestionar' => $request->user()->tienePermiso('CONTABILIDAD_GESTIONAR'),
            ];
        }

        return view('contabilidad.index', array_merge(['preparada' => $preparada, 'sync' => $sync], $datos));
    }

    public function preparar(Request $request)
    {
        $d = $request->validate(['inicio' => ['required', 'date', 'before_or_equal:today']]);

        $nuevas = $this->c->preparar($d['inicio']);
        AuditoriaService::registrar('CONTABILIDAD_PREPARADA', 'cont_cuentas', null, ['inicio' => $d['inicio'], 'cuentas_nuevas' => $nuevas]);

        return redirect()->route('contabilidad.index')->with('success', 'Contabilidad lista: se contabiliza todo lo ocurrido desde el '.Carbon::parse($d['inicio'])->format('d/m/Y').'.');
    }

    public function sincronizar()
    {
        $r = $this->alDia();
        if ($r['errores']) {
            return back()->with('error', 'Se contabilizaron '.$r['generados'].' operaciones, pero hubo errores: '.implode(' | ', array_slice($r['errores'], 0, 3)));
        }

        return back()->with('success', 'Contabilidad al día: '.$r['generados'].' operaciones nuevas contabilizadas.');
    }

    // ------------------------------------------------------------------ plan de cuentas y mapeos

    public function plan()
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }

        $cuentas = DB::table('cont_cuentas')->orderBy('cue_codigo')->get();

        return view('contabilidad.plan', ['cuentas' => $cuentas, 'tipos' => ContabilidadService::TIPOS]);
    }

    public function cuentaGuardar(Request $request)
    {
        $d = $request->validate([
            'codigo' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:'.implode(',', array_keys(ContabilidadService::TIPOS))],
            'imputable' => ['nullable', 'boolean'],
        ], ['codigo.regex' => 'El código solo puede tener números, letras, puntos y guiones.']);

        if (DB::table('cont_cuentas')->where('cue_codigo', $d['codigo'])->exists()) {
            return back()->withInput()->with('error', 'Ya existe una cuenta con el código '.$d['codigo'].'.');
        }

        $id = DB::table('cont_cuentas')->insertGetId([
            'cue_codigo' => $d['codigo'], 'cue_nombre' => trim($d['nombre']), 'cue_tipo' => $d['tipo'],
            'cue_imputable' => $request->boolean('imputable', true), 'cue_activa' => true, 'cue_clave' => null,
        ], 'cue_id');
        AuditoriaService::registrar('CUENTA_CREADA', 'cont_cuentas', $id, $d);

        return back()->with('success', 'Cuenta creada.');
    }

    public function cuentaActualizar(Request $request, int $id)
    {
        $d = $request->validate(['nombre' => ['required', 'string', 'max:120'], 'activa' => ['nullable', 'boolean']]);
        $cuenta = DB::table('cont_cuentas')->where('cue_id', $id)->first();
        abort_unless($cuenta, 404);

        $activa = $request->boolean('activa');
        if (! $activa && $cuenta->cue_clave) {
            return back()->with('error', 'La cuenta «'.$cuenta->cue_nombre.'» la usa el sistema: no se puede desactivar. Podés cambiarle el nombre.');
        }
        if (! $activa && DB::table('cont_mapeos')->where('cue_id', $id)->exists()) {
            return back()->with('error', 'Esa cuenta está asignada a una forma de pago o categoría. Cambiá primero esa asignación en Mapeos.');
        }

        DB::table('cont_cuentas')->where('cue_id', $id)->update(['cue_nombre' => trim($d['nombre']), 'cue_activa' => $activa]);
        AuditoriaService::registrar('CUENTA_EDITADA', 'cont_cuentas', $id, ['nombre' => $d['nombre'], 'activa' => $activa]);

        return back()->with('success', 'Cuenta actualizada.');
    }

    public function mapeos()
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }

        $cuentas = DB::table('cont_cuentas')->where('cue_imputable', true)->where('cue_activa', true)->orderBy('cue_codigo')->get();
        $map = DB::table('cont_mapeos')->get()->mapWithKeys(fn ($m) => [$m->map_tipo.'|'.$m->map_clave => (int) $m->cue_id]);
        $porClave = DB::table('cont_cuentas')->whereNotNull('cue_clave')->pluck('cue_id', 'cue_clave');

        return view('contabilidad.mapeos', [
            'cuentas' => $cuentas,
            'map' => $map,
            'pagos' => ContabilidadService::PAGOS,
            'defectoPago' => collect(ContabilidadService::PAGOS)->map(fn ($p) => $porClave[$p[1]] ?? null),
            'categorias' => DB::table('categorias')->orderBy('cat_nombre')->get(),
            'defectos' => ['CAT_INGRESO' => $porClave['ventas_mercaderias'] ?? null, 'CAT_INVENTARIO' => $porClave['mercaderias'] ?? null, 'CAT_COSTO' => $porClave['cmv'] ?? null],
        ]);
    }

    public function mapeosGuardar(Request $request)
    {
        $validas = DB::table('cont_cuentas')->where('cue_imputable', true)->where('cue_activa', true)->pluck('cue_id')->all();
        $filas = [];

        foreach ((array) $request->input('pago', []) as $clave => $cue) {
            if (isset(ContabilidadService::PAGOS[$clave])) {
                $filas[] = ['PAGO', (string) $clave, $cue];
            }
        }
        $catIds = DB::table('categorias')->pluck('cat_id')->map(fn ($v) => (string) $v)->all();
        foreach (['ingreso' => 'CAT_INGRESO', 'inventario' => 'CAT_INVENTARIO', 'costo' => 'CAT_COSTO'] as $campo => $tipo) {
            foreach ((array) $request->input('cat_'.$campo, []) as $catId => $cue) {
                if (in_array((string) $catId, $catIds, true)) {
                    $filas[] = [$tipo, (string) $catId, $cue];
                }
            }
        }

        foreach ($filas as [, , $cue]) {
            if ($cue !== null && $cue !== '' && ! in_array((int) $cue, $validas, true)) {
                return back()->with('error', 'Elegiste una cuenta que no existe o no se puede usar.');
            }
        }

        DB::transaction(function () use ($filas) {
            foreach ($filas as [$tipo, $clave, $cue]) {
                $q = DB::table('cont_mapeos')->where('map_tipo', $tipo)->where('map_clave', $clave);
                if ($cue === null || $cue === '') {
                    $q->delete();
                } elseif ($q->exists()) {
                    $q->update(['cue_id' => (int) $cue]);
                } else {
                    DB::table('cont_mapeos')->insert(['map_tipo' => $tipo, 'map_clave' => $clave, 'cue_id' => (int) $cue]);
                }
            }
            AuditoriaService::registrar('CONTABILIDAD_MAPEOS', 'cont_mapeos', null, ['filas' => count($filas)]);
        });
        $this->c->olvidarCache();

        return back()->with('success', 'Asignaciones guardadas. Valen para los asientos que se generen desde ahora.');
    }

    // ------------------------------------------------------------------ libros y balances

    public function diario(Request $request)
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }
        $sync = $this->alDia();
        [$desde, $hasta] = $this->rango($request);
        $origen = $request->input('origen');
        [$asientos, $lineas] = $this->inf->diario($desde, $hasta, $origen ?: null, $request->input('q'));

        return view('contabilidad.diario', [
            'asientos' => $asientos, 'lineas' => $lineas, 'desde' => $desde, 'hasta' => $hasta, 'origen' => $origen, 'sync' => $sync,
            'origenes' => DB::table('cont_asientos')->distinct()->orderBy('asi_origen')->pluck('asi_origen'),
            'puedeGestionar' => $request->user()->tienePermiso('CONTABILIDAD_GESTIONAR'),
        ]);
    }

    public function mayor(Request $request)
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }
        $this->alDia();
        [$desde, $hasta] = $this->rango($request);
        $cuentas = DB::table('cont_cuentas')->where('cue_imputable', true)->orderBy('cue_codigo')->get();
        $m = $request->filled('cuenta') && $cuentas->contains('cue_id', (int) $request->cuenta)
            ? $this->inf->mayor((int) $request->cuenta, $desde, $hasta) : null;

        if ($m && $request->input('exportar') === 'csv') {
            return $this->csv('libro-mayor-'.$m['cuenta']->cue_codigo.'.csv', ['Fecha', 'Asiento', 'Concepto', 'Debe', 'Haber', 'Saldo'],
                $m['filas']->map(fn ($f) => [$f->asi_fecha, $f->asi_numero, $f->asi_glosa, $f->lin_debe, $f->lin_haber, $f->saldo]));
        }

        return view('contabilidad.mayor', ['cuentas' => $cuentas, 'm' => $m, 'desde' => $desde, 'hasta' => $hasta, 'cuentaId' => (int) $request->cuenta]);
    }

    public function sumas(Request $request)
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }
        $this->alDia();
        [$desde, $hasta] = $this->rango($request, now()->startOfYear()->toDateString());
        $filas = $this->inf->sumas($desde, $hasta);

        if ($request->input('exportar') === 'csv') {
            return $this->csv('balance-sumas-y-saldos.csv', ['Código', 'Cuenta', 'Debe', 'Haber', 'Saldo deudor', 'Saldo acreedor'],
                $filas->map(fn ($f) => [$f->cue_codigo, $f->cue_nombre, $f->debe, $f->haber, $f->saldo_deudor, $f->saldo_acreedor]));
        }

        return view('contabilidad.sumas', ['filas' => $filas, 'desde' => $desde, 'hasta' => $hasta]);
    }

    public function resultados(Request $request)
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }
        $this->alDia();
        [$desde, $hasta] = $this->rango($request, now()->startOfYear()->toDateString());

        return view('contabilidad.resultados', ['r' => $this->inf->resultados($desde, $hasta), 'desde' => $desde, 'hasta' => $hasta]);
    }

    public function balance(Request $request)
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }
        $this->alDia();
        $request->validate(['hasta' => ['nullable', 'date']]);
        $hasta = $request->filled('hasta') ? Carbon::parse($request->hasta)->toDateString() : now()->toDateString();

        return view('contabilidad.balance', ['b' => $this->inf->balance($hasta), 'hasta' => $hasta]);
    }

    public function libroIva(Request $request, string $libro)
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }
        [$desde, $hasta] = $this->rango($request);
        $filas = $libro === 'ventas' ? $this->inf->libroIvaVentas($desde, $hasta) : $this->inf->libroIvaCompras($desde, $hasta);

        if ($request->input('exportar') === 'csv') {
            return $this->csv("libro-iva-{$libro}-{$desde}-a-{$hasta}.csv",
                ['Fecha', 'Tipo', 'Timbrado', 'Número', 'RUC/CI', $libro === 'ventas' ? 'Cliente' : 'Proveedor', 'Gravado 10%', 'IVA 10%', 'Gravado 5%', 'IVA 5%', 'Exento', 'Total'],
                array_map(fn ($f) => [$f['fecha'], $f['tipo'], $f['timbrado'], $f['numero'], $f['ruc'], $f['nombre'], $f['base10'], $f['iva10'], $f['base5'], $f['iva5'], $f['exento'], $f['total']], $filas));
        }

        $tot = [];
        foreach (['base10', 'iva10', 'base5', 'iva5', 'exento', 'total'] as $k) {
            $tot[$k] = round(array_sum(array_column($filas, $k)), 2);
        }

        return view('contabilidad.iva', ['libro' => $libro, 'filas' => $filas, 'tot' => $tot, 'desde' => $desde, 'hasta' => $hasta]);
    }

    // ------------------------------------------------------------------ asientos manuales y cierre

    public function asientoNuevo()
    {
        if ($r = $this->exigirPreparada()) {
            return $r;
        }

        $cuentas = DB::table('cont_cuentas')->where('cue_imputable', true)->where('cue_activa', true)->orderBy('cue_codigo')->get(['cue_id', 'cue_codigo', 'cue_nombre', 'cue_clave']);

        return view('contabilidad.asiento', [
            'cuentas' => $cuentas,
            'claves' => $cuentas->whereNotNull('cue_clave')->pluck('cue_id', 'cue_clave'),
            'inventario' => $this->inf->valorInventario(),
            'cerradoHasta' => $this->c->cerradoHasta()?->toDateString(),
        ]);
    }

    public function asientoGuardar(Request $request)
    {
        $d = $request->validate([
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'glosa' => ['required', 'string', 'min:5', 'max:255'],
            'lineas' => ['required', 'array', 'min:2', 'max:60'],
            'lineas.*.cue_id' => ['nullable', 'integer'],
            'lineas.*.debe' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lineas.*.haber' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'lineas.*.detalle' => ['nullable', 'string', 'max:160'],
        ], ['glosa.min' => 'Explicá el motivo del asiento (mínimo 5 letras): queda como respaldo.']);

        $lineas = [];
        foreach ($d['lineas'] as $l) {
            if (empty($l['cue_id'])) {
                continue;
            }
            $lineas[] = [(int) $l['cue_id'], (float) ($l['debe'] ?? 0), (float) ($l['haber'] ?? 0), $l['detalle'] ?? null];
        }

        try {
            $id = $this->c->crearAsiento($d['fecha'], trim($d['glosa']), 'MANUAL', null, $lineas, $request->user()->usu_id, true);
            AuditoriaService::registrar('ASIENTO_MANUAL', 'cont_asientos', $id, ['glosa' => $d['glosa']]);
        } catch (NegocioException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $codigo = strtoupper(Str::random(6));
            Log::error("Error al crear asiento manual [{$codigo}]", ['exception' => $e]);

            return back()->withInput()->with('error', "No se pudo guardar el asiento. Avisá al administrador (código {$codigo}).");
        }

        return redirect()->route('contabilidad.diario', ['desde' => $d['fecha'], 'hasta' => $d['fecha']])->with('success', 'Asiento registrado.');
    }

    public function asientoAnular(Request $request, int $id)
    {
        $d = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:150']], ['motivo.required' => 'Indicá el motivo de la anulación.', 'motivo.min' => 'Indicá el motivo de la anulación.']);

        try {
            $this->c->anularManual($id, $d['motivo'], $request->user()->usu_id);
        } catch (NegocioException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Asiento anulado con un contra-asiento.');
    }

    public function cerrarPeriodo(Request $request)
    {
        $d = $request->validate(['hasta' => ['nullable', 'date', 'before:today'], 'reabrir' => ['nullable', 'boolean']]);

        if ($request->boolean('reabrir')) {
            ConfiguracionService::set(['cont_cerrado_hasta' => null]);
            AuditoriaService::registrar('PERIODO_REABIERTO', 'configuracion_sistema', null, ['antes' => $this->c->cerradoHasta()?->toDateString()]);

            return back()->with('success', 'Se reabrieron los períodos: ya no hay fecha de cierre.');
        }

        if (empty($d['hasta'])) {
            return back()->with('error', 'Indicá hasta qué fecha se cierra.');
        }
        $actual = $this->c->cerradoHasta();
        if ($actual && Carbon::parse($d['hasta'])->lte($actual)) {
            return back()->with('error', 'Ese período ya está cerrado (hasta el '.$actual->format('d/m/Y').').');
        }

        ConfiguracionService::set(['cont_cerrado_hasta' => Carbon::parse($d['hasta'])->toDateString()]);
        AuditoriaService::registrar('PERIODO_CERRADO', 'configuracion_sistema', null, ['hasta' => $d['hasta']]);

        return back()->with('success', 'Período cerrado hasta el '.Carbon::parse($d['hasta'])->format('d/m/Y').'. Lo que ocurra con fecha anterior se contabiliza en el primer día abierto.');
    }
}
