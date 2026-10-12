<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PadronRuc;
use App\Services\PadronImportador;
use Illuminate\Http\Request;

class PadronController extends Controller
{
    /** Busca por RUC (con o sin guion y dígito verificador). Devuelve el cliente ya cargado, si existe, y coincidencias del padrón. */
    public function buscar(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $ruc = $q;
        $dv = null;
        if (preg_match('/^(\d{1,12})\s*-\s*(\d?)$/', $q, $m)) {
            $ruc = $m[1];
            $dv = $m[2] !== '' ? $m[2] : null;
        }
        if (! preg_match('/^\d{3,12}$/', $ruc)) {
            return response()->json(['existente' => null, 'padron' => []]);
        }

        $existente = Cliente::where('cli_ruc_ci', $q)->orWhere('cli_ruc_ci', $ruc)
            ->orWhere('cli_ruc_ci', 'like', $ruc.'-%')->first();

        $padron = PadronRuc::where('pad_ruc', 'like', $ruc.'%')->orderBy('pad_ruc')->limit(8)->get()
            ->filter(fn ($p) => $dv === null || $p->pad_dv === $dv || $p->pad_dv === null)
            ->map(function ($p) {
                // Las personas vienen como "APELLIDOS, NOMBRES"; las empresas, con la razón social completa.
                $partes = array_map('trim', explode(',', $p->pad_nombre, 2));
                $esPersona = count($partes) === 2 && $partes[0] !== '' && $partes[1] !== '';

                return [
                    'ruc' => $p->pad_ruc.($p->pad_dv !== null ? '-'.$p->pad_dv : ''),
                    'nombre' => $esPersona ? $partes[1] : $p->pad_nombre,
                    'apellido' => $esPersona ? $partes[0] : '',
                    'completo' => $p->pad_nombre,
                    'estado' => $p->pad_estado,
                ];
            })->values();

        return response()->json(['existente' => $existente, 'padron' => $padron]);
    }

    /** Carga archivos del padrón (txt, csv o zip) subidos desde la pantalla de Clientes. */
    public function importar(Request $request)
    {
        $request->validate([
            'archivos' => ['required', 'array', 'min:1', 'max:12'],
            'archivos.*' => ['file', 'max:204800', 'extensions:txt,csv,zip'],
        ], [
            'archivos.required' => 'Elegí al menos un archivo del padrón.',
            'archivos.*.extensions' => 'Solo se aceptan archivos .txt, .csv o .zip.',
            'archivos.*.max' => 'Un archivo supera los 200 MB.',
            'archivos.*.uploaded' => 'No se pudo subir el archivo (revisá upload_max_filesize y post_max_size en php.ini).',
        ]);

        @set_time_limit(0);
        $total = 0;
        try {
            foreach ($request->file('archivos') as $f) {
                $nombre = strtolower($f->getClientOriginalExtension()) === 'zip' ? 'padron.zip' : 'padron.txt';
                [$leidas] = PadronImportador::importar($f->move(sys_get_temp_dir(), uniqid('padron_', true).'_'.$nombre)->getPathname());
                $total += $leidas;
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo importar el padrón: '.$e->getMessage());
        }

        return back()->with('padron_ok', number_format($total, 0, ',', '.').' registros cargados. Ya podés buscar por RUC al registrar clientes.');
    }
}
