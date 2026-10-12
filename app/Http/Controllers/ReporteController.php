<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use App\Reportes\Catalogo;
use App\Reportes\Exportador;
use App\Reportes\Tablero;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Centro de reportes: pantalla, Excel (.xlsx) y PDF, con desglose por sucursal. */
class ReporteController extends Controller
{
    private const MAX_PANTALLA = 300;

    public function index(Request $request)
    {
        $grupos = [];
        foreach (Catalogo::GRUPOS as $clave => $g) {
            if (! $request->user()->tienePermiso($g['permiso'])) {
                continue;
            }
            $g['reportes'] = array_filter(Catalogo::todos(), fn ($r) => $r['grupo'] === $clave);
            $grupos[$clave] = $g;
        }

        $sucursales = Sucursal::where('suc_activa', true)->orderBy('suc_nombre')->get();
        $sucId = $request->integer('suc_id') ?: null;
        $tablero = $grupos ? Tablero::datos($request->user(), $sucId) : null;

        return view('reportes.index', compact('grupos', 'tablero', 'sucursales', 'sucId'));
    }

    public function ver(Request $request, string $clave)
    {
        $def = $this->definicion($request, $clave);
        $f = $this->filtros($request, $def);
        $doc = $this->documento($request, $clave, $def, $f);

        foreach ($doc['secciones'] as &$s) {
            $s['totales'] = [];
            foreach ($s['columnas'] as $i => $c) {
                if (! empty($c['total'])) {
                    $s['totales'][$i] = array_sum(array_map(fn ($fila) => (float) ($fila[$i] ?? 0), $s['filas']));
                }
            }
            $s['cantidad'] = count($s['filas']);
            $s['filas'] = array_slice($s['filas'], 0, self::MAX_PANTALLA);
        }
        unset($s);

        return view('reportes.ver', [
            'clave' => $clave, 'def' => $def, 'f' => $f, 'doc' => $doc, 'max' => self::MAX_PANTALLA,
            'sucursales' => Sucursal::where('suc_activa', true)->orderBy('suc_nombre')->get(),
            'usuarios' => in_array('usuario', $def['filtros'], true) ? \App\Models\User::where('usu_activo', true)->orderBy('usu_nombre')->get() : collect(),
            'puedeExportar' => $request->user()->tienePermiso('REPORTES_EXPORTAR'),
        ]);
    }

    public function xlsx(Request $request, string $clave)
    {
        [$doc] = $this->preparar($request, $clave, 'XLSX');

        return response(Exportador::xlsx($doc), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$this->archivo($clave, 'xlsx').'"',
        ]);
    }

    public function pdf(Request $request, string $clave)
    {
        [$doc] = $this->preparar($request, $clave, 'PDF');

        return response(Exportador::pdf($doc), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->archivo($clave, 'pdf').'"',
        ]);
    }

    // ------------------------------------------------------------------ internos

    private function preparar(Request $request, string $clave, string $formato): array
    {
        abort_unless($request->user()->tienePermiso('REPORTES_EXPORTAR'), 403, 'No tenés permiso para exportar reportes.');
        $def = $this->definicion($request, $clave);
        $f = $this->filtros($request, $def);
        $doc = $this->documento($request, $clave, $def, $f);

        AuditoriaService::registrar('REPORTE_EXPORTADO', 'reportes', $clave, ['formato' => $formato, 'filtros' => $f]);

        return [$doc, $def];
    }

    private function archivo(string $clave, string $ext): string
    {
        return Str::slug(Catalogo::todos()[$clave]['nombre'], '_').'_'.now()->format('Ymd_His').'.'.$ext;
    }

    private function definicion(Request $request, string $clave): array
    {
        $def = Catalogo::todos()[$clave] ?? null;
        abort_unless($def, 404);
        $permiso = Catalogo::GRUPOS[$def['grupo']]['permiso'];
        abort_unless($request->user()->tienePermiso($permiso), 403, 'No tenés permiso para ver este reporte.');

        return $def;
    }

    private function filtros(Request $request, array $def): array
    {
        $v = $request->validate([
            'desde' => ['nullable', 'date'], 'hasta' => ['nullable', 'date'], 'suc_id' => ['nullable', 'integer'],
            'agrupar' => ['nullable', 'string', 'max:20'], 'dias' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'producto' => ['nullable', 'string', 'max:80'], 'usu_id' => ['nullable', 'integer'], 'cliente' => ['nullable', 'string', 'max:80'],
        ]);

        $hoy = Carbon::today();
        $desde = Carbon::parse($v['desde'] ?? $hoy->copy()->startOfMonth());
        $hasta = Carbon::parse($v['hasta'] ?? $hoy);
        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $f = ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d'), 'suc_id' => $v['suc_id'] ?? null, 'producto' => $v['producto'] ?? '', 'cliente' => $v['cliente'] ?? '', 'usu_id' => in_array('usuario', $def['filtros'], true) ? ($v['usu_id'] ?? null) : null];

        $filtros = $def['filtros'];
        if (isset($filtros['agrupar'])) {
            $f['agrupar'] = array_key_exists($v['agrupar'] ?? '', $filtros['agrupar']) ? $v['agrupar'] : array_key_first($filtros['agrupar']);
        }
        if (isset($filtros['dias'])) {
            $f['dias'] = (int) ($v['dias'] ?? $filtros['dias'][1]);
        }

        return $f;
    }

    private function documento(Request $request, string $clave, array $def, array $f): array
    {
        $verCostos = $request->user()->tienePermiso('REPORTES_COSTOS');
        $activas = Sucursal::where('suc_activa', true)->orderBy('suc_nombre')->get();

        $secciones = [];
        $armar = function (string $titulo, ?int $suc, ?string $hoja = null) use ($clave, $f, $verCostos, &$secciones) {
            $r = Catalogo::ejecutar($clave, $f, $suc);
            if (! $verCostos) {
                $r = Catalogo::sinCostos($r);
            }
            $secciones[] = ['titulo' => $titulo, 'hoja' => $hoja ?? $titulo, 'columnas' => $r['columnas'], 'filas' => $r['filas']];
        };

        if (! empty($def['global'])) {
            $armar('Todo el negocio', null, 'Todo el negocio');
        } elseif ($f['suc_id']) {
            $s = Sucursal::find($f['suc_id']);
            abort_unless($s, 404);
            $armar('Sucursal: '.$s->suc_nombre, (int) $s->suc_id, $s->suc_nombre);
        } elseif ($activas->count() > 1) {
            $armar('Consolidado (todas las sucursales)', null, 'Consolidado');
            foreach ($activas as $s) {
                $armar('Sucursal: '.$s->suc_nombre, (int) $s->suc_id, $s->suc_nombre);
            }
        } else {
            $armar($activas->first()?->suc_nombre ?? 'Todo el negocio', $activas->first()?->suc_id);
        }

        $partes = [];
        if (isset($def['filtros']) && in_array('fechas', $def['filtros'], true)) {
            $partes[] = 'Del '.Carbon::parse($f['desde'])->format('d/m/Y').' al '.Carbon::parse($f['hasta'])->format('d/m/Y');
        }
        if (isset($f['agrupar'])) {
            $partes[] = $def['filtros']['agrupar'][$f['agrupar']];
        }
        if (isset($f['dias'])) {
            $partes[] = $def['filtros']['dias'][0].': '.$f['dias'];
        }
        if (trim($f['producto']) !== '' && in_array('producto', $def['filtros'], true)) {
            $partes[] = 'Producto: '.$f['producto'];
        }
        if (trim($f['cliente']) !== '' && in_array('cliente', $def['filtros'], true)) {
            $partes[] = 'Cliente: '.$f['cliente'];
        }
        if (! empty($f['usu_id'])) {
            $uu = \App\Models\User::find($f['usu_id']);
            $partes[] = 'Usuario: '.trim(($uu->usu_nombre ?? '').' '.($uu->usu_apellido ?? ''));
        }
        if (! $partes) {
            $partes[] = 'Situación al '.now()->format('d/m/Y');
        }

        $u = $request->user();

        return [
            'negocio' => ConfiguracionService::nombreNegocio(),
            'titulo' => $def['nombre'],
            'subtitulo' => implode('  ·  ', $partes),
            'generado' => now()->format('d/m/Y H:i'),
            'usuario' => trim(($u->usu_nombre ?? '').' '.($u->usu_apellido ?? '')) ?: ($u->usu_usuario ?? ''),
            'secciones' => $secciones,
        ];
    }
}
