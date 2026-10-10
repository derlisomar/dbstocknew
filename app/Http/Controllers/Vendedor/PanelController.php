<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService as Cfg;
use App\Services\LicenciaService;
use App\Services\SaludService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PanelController extends Controller
{
    /** Tablas que tiene que haber para que el sistema esté completo. */
    private const TABLAS = ['stock_movimientos', 'compras', 'cuentas_pagar', 'presupuestos', 'configuracion_sistema', 'planes', 'licencia_pagos'];

    public function resumen()
    {
        $lista = $this->listaPreparacion();
        $hechos = collect($lista)->where('ok', true)->count();

        return view('vendedor.resumen', [
            'lista' => $lista,
            'hechos' => $hechos,
            'total' => count($lista),
            'licencia' => LicenciaService::estado(),
            'edicion' => Cfg::edicion(),
            'salud' => SaludService::chequeos(),
            'cifras' => $this->cifras(),
        ]);
    }

    /** Números rápidos del negocio. Cada uno se calcula por separado: si falta una tabla, solo ese queda en blanco. */
    private function cifras(): array
    {
        $contar = function (callable $f) {
            try {
                return $f();
            } catch (\Throwable) {
                return null;
            }
        };

        return [
            'usuarios' => $contar(fn () => DB::table('usuarios')->where('usu_activo', true)->count()),
            'sucursales' => $contar(fn () => DB::table('sucursales')->where('suc_activa', true)->count()),
            'cajas' => $contar(fn () => DB::table('cajas')->where('caj_activa', true)->count()),
            'productos' => $contar(fn () => DB::table('productos')->count()),
            'ventas_mes' => $contar(fn () => DB::table('ventas')->whereNull('vta_anulada_por')->where('vta_fecha', '>=', now()->startOfMonth())->count()),
            'total_mes' => $contar(fn () => (float) DB::table('ventas')->whereNull('vta_anulada_por')->where('vta_fecha', '>=', now()->startOfMonth())->sum('vta_total')),
        ];
    }

    // ------------------------------------------------------------------ negocio

    public function negocio()
    {
        return view('vendedor.negocio', ['cfg' => Cfg::todo(), 'logo' => Cfg::logoUrl()]);
    }

    public function guardarNegocio(Request $request)
    {
        $d = $request->validate([
            'negocio_nombre' => ['required', 'string', 'max:120'],
            'negocio_ruc' => ['nullable', 'string', 'max:30'],
            'negocio_direccion' => ['nullable', 'string', 'max:200'],
            'negocio_telefono' => ['nullable', 'string', 'max:40'],
            'negocio_email' => ['nullable', 'email', 'max:150'],
            'negocio_pie_ticket' => ['nullable', 'string', 'max:200'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'quitar_logo' => ['nullable', 'boolean'],
        ], [
            'negocio_nombre.required' => 'Escribí el nombre del negocio.',
            'logo.mimes' => 'El logo debe ser una imagen PNG, JPG o WEBP.',
            'logo.max' => 'El logo no puede pesar más de 1 MB.',
        ]);

        $pares = collect($d)->only(['negocio_nombre', 'negocio_ruc', 'negocio_direccion', 'negocio_telefono', 'negocio_email', 'negocio_pie_ticket'])->all();

        $carpeta = config('vendedor.carpeta_logo');
        if ($request->hasFile('logo')) {
            File::ensureDirectoryExists($carpeta);
            foreach (File::glob($carpeta.'/logo.*') as $viejo) {
                File::delete($viejo);
            }
            $ext = strtolower($request->file('logo')->guessExtension() ?: $request->file('logo')->getClientOriginalExtension());
            $request->file('logo')->move($carpeta, 'logo.'.$ext);
            $pares['negocio_logo'] = 'img/negocio/logo.'.$ext;
            $pares['negocio_logo_version'] = (string) time();
        } elseif ($request->boolean('quitar_logo')) {
            foreach (File::glob($carpeta.'/logo.*') as $viejo) {
                File::delete($viejo);
            }
            $pares['negocio_logo'] = null;
        }

        Cfg::set($pares);
        AuditoriaService::registrar('VENDEDOR_NEGOCIO', 'configuracion_sistema', null, ['por' => 'vendedor', 'nombre' => $d['negocio_nombre']]);

        return back()->with('success', 'Datos del negocio guardados.');
    }

    // ------------------------------------------------------------------ edición y módulos

    public function modulos()
    {
        $activos = [];
        foreach (array_keys(config('modulos.catalogo')) as $clave) {
            if (Cfg::modulo($clave)) {
                $activos[] = $clave;
            }
        }

        return view('vendedor.modulos', [
            'edicion' => Cfg::edicion(),
            'activos' => $activos,
            'limites' => ['sucursales' => Cfg::limite('sucursales'), 'cajas' => Cfg::limite('cajas'), 'usuarios' => Cfg::limite('usuarios')],
        ]);
    }

    public function aplicarEdicion(Request $request)
    {
        $d = $request->validate(['edicion' => ['required', 'in:'.implode(',', array_keys(config('modulos.ediciones')))]]);

        Cfg::set(['edicion' => $d['edicion']]);
        Cfg::fijarModulos(Cfg::modulosDeEdicion($d['edicion']));
        AuditoriaService::registrar('VENDEDOR_EDICION', 'configuracion_sistema', null, ['por' => 'vendedor', 'edicion' => $d['edicion']]);

        return back()->with('success', 'Edición '.config('modulos.ediciones')[$d['edicion']].' aplicada con sus módulos por defecto. Si querés, ajustá módulos sueltos abajo.');
    }

    public function guardarModulos(Request $request)
    {
        $d = $request->validate([
            'modulos' => ['nullable', 'array'],
            'modulos.*' => ['string'],
            'limite_sucursales' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'limite_cajas' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'limite_usuarios' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);

        $validos = array_keys(config('modulos.catalogo'));
        Cfg::fijarModulos(array_values(array_intersect($d['modulos'] ?? [], $validos)));
        Cfg::set([
            'limite_sucursales' => (int) ($d['limite_sucursales'] ?? 0) ?: null,
            'limite_cajas' => (int) ($d['limite_cajas'] ?? 0) ?: null,
            'limite_usuarios' => (int) ($d['limite_usuarios'] ?? 0) ?: null,
        ]);
        AuditoriaService::registrar('VENDEDOR_MODULOS', 'configuracion_sistema', null, ['por' => 'vendedor', 'modulos' => $d['modulos'] ?? []]);

        return back()->with('success', 'Módulos y límites guardados.');
    }

    // ------------------------------------------------------------------ herramientas

    public function herramientas()
    {
        $faltan = array_values(array_filter(self::TABLAS, fn ($t) => ! Schema::hasTable($t)));

        $hayAdmin = false;
        try {
            $hayAdmin = DB::table('usuarios')->join('roles', 'roles.rol_id', '=', 'usuarios.rol_id')
                ->whereRaw('LOWER(roles.rol_nombre) = ?', ['administrador'])->where('usuarios.usu_activo', true)->exists();
        } catch (\Throwable) {
        }

        return view('vendedor.herramientas', ['faltan' => $faltan, 'salida' => session('salida'), 'hayAdmin' => $hayAdmin]);
    }

    public function prepararBase()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $salida = trim(Artisan::output());
            Artisan::call('permisos:sincronizar');
            $salida .= "\n\n".trim(Artisan::output());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo preparar la base de datos: '.$e->getMessage());
        }

        AuditoriaService::registrar('VENDEDOR_PREPARAR_BASE', null, null, ['por' => 'vendedor']);

        return back()->with('success', 'Base de datos preparada: tablas al día y permisos sincronizados.')->with('salida', $salida);
    }

    public function verificar()
    {
        Artisan::call('sistema:verificar');

        return back()->with('salida', trim(Artisan::output()));
    }

    public function limpiarCache()
    {
        Artisan::call('optimize:clear');

        return back()->with('success', 'Caché limpiada.')->with('salida', trim(Artisan::output()));
    }

    // ------------------------------------------------------------------ lista de preparación

    /** @return list<array{ok:bool, titulo:string, detalle:string, enlace:string}> */
    private function listaPreparacion(): array
    {
        $faltan = array_filter(self::TABLAS, fn ($t) => ! Schema::hasTable($t));
        $nSuc = Schema::hasTable('sucursales') ? DB::table('sucursales')->where('suc_activa', true)->count() : 0;
        $nCaj = Schema::hasTable('cajas') ? DB::table('cajas')->where('caj_activa', true)->count() : 0;
        $nAdm = DB::table('usuarios')->join('roles', 'roles.rol_id', '=', 'usuarios.rol_id')
            ->whereRaw('LOWER(roles.rol_nombre) = ?', ['administrador'])->where('usuarios.usu_activo', true)->count();

        $lista = [
            ['ok' => $faltan === [], 'titulo' => 'Base de datos al día', 'detalle' => $faltan === [] ? 'Todas las tablas están creadas.' : 'Faltan tablas: '.implode(', ', $faltan).'. Usá "Preparar base de datos".', 'enlace' => route('vendedor.herramientas')],
            ['ok' => (bool) Cfg::get('negocio_nombre'), 'titulo' => 'Datos del negocio', 'detalle' => Cfg::get('negocio_nombre') ? Cfg::get('negocio_nombre') : 'Falta el nombre del negocio.', 'enlace' => route('vendedor.negocio')],
            ['ok' => (bool) Cfg::get('lic_plan_id') || Cfg::get('edicion') !== null, 'titulo' => 'Edición o plan elegido', 'detalle' => 'Edición '.config('modulos.ediciones')[Cfg::edicion()].(Cfg::get('lic_plan_id') ? ' (con plan asignado).' : ' (sin plan asignado).'), 'enlace' => route('vendedor.licencia')],
            ['ok' => $nSuc > 0, 'titulo' => 'Al menos una sucursal', 'detalle' => "{$nSuc} sucursal(es) activa(s).", 'enlace' => route('vendedor.estructura')],
            ['ok' => $nCaj > 0, 'titulo' => 'Al menos una caja', 'detalle' => "{$nCaj} caja(s) activa(s).", 'enlace' => route('vendedor.estructura')],
            ['ok' => $nAdm > 0, 'titulo' => 'Usuario administrador del negocio', 'detalle' => "{$nAdm} administrador(es) activo(s).", 'enlace' => route('vendedor.usuarios')],
        ];

        if (Cfg::modulo('multimoneda')) {
            $cot = Schema::hasTable('cotizaciones') && DB::table('cotizaciones')->where('cot_activa', true)->exists();
            $lista[] = ['ok' => $cot, 'titulo' => 'Cotización del dólar y del real', 'detalle' => $cot ? 'Hay una cotización activa.' : 'Falta cargar la cotización (el módulo de varias monedas está activo).', 'enlace' => route('vendedor.estructura')];
        }

        $lic = LicenciaService::estado();
        $lista[] = [
            'ok' => $lic['estado'] !== 'SIN_LICENCIA' && ($lic['tipo'] === 'PERPETUA' || $lic['vence']),
            'titulo' => 'Licencia con vencimiento',
            'detalle' => $lic['estado'] === 'SIN_LICENCIA' ? 'Sin licencia configurada: el sistema funciona sin vencimiento.' : ($lic['tipo'] === 'PERPETUA' ? 'Licencia permanente.' : ($lic['vence'] ? 'Vence el '.\Carbon\Carbon::parse($lic['vence'])->format('d/m/Y').'.' : 'Falta registrar el primer pago o la fecha de vencimiento.')),
            'enlace' => route('vendedor.licencia'),
        ];

        return $lista;
    }
}
