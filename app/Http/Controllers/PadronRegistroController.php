<?php

namespace App\Http\Controllers;

use App\Models\PadronRuc;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Listado y mantenimiento manual del padrón de RUC (Registros y Catálogos → Padrón de RUC). */
class PadronRegistroController extends Controller
{
    public const ESTADOS = ['ACTIVO', 'SUSPENSION TEMPORAL', 'CANCELADO', 'BLOQUEADO'];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', '');

        $consulta = PadronRuc::query();
        if ($q !== '') {
            if (preg_match('/^\d[\d\-]*$/', $q)) {
                $consulta->where('pad_ruc', 'like', rtrim(explode('-', $q)[0]).'%');
            } else {
                foreach (preg_split('/\s+/', mb_strtolower($q)) as $palabra) {
                    $consulta->whereRaw('lower(pad_nombre) like ?', ['%'.str_replace(['%', '_'], ['\\%', '\\_'], $palabra).'%']);
                }
            }
        }
        if ($estado === 'OTRO') {
            $consulta->where(fn ($w) => $w->whereNull('pad_estado')->orWhereNotIn('pad_estado', self::ESTADOS));
        } elseif (in_array($estado, self::ESTADOS, true)) {
            $consulta->where('pad_estado', $estado);
        }

        return view('padron.index', [
            'registros' => $consulta->orderBy('pad_ruc')->paginate(25)->withQueryString(),
            'estados' => self::ESTADOS,
            'q' => $q,
            'estado' => $estado,
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->reglas() + ['pad_ruc' => ['required', 'regex:/^\d{1,12}[A-Za-z]?$/', 'unique:padron_ruc,pad_ruc']], $this->mensajes());
        PadronRuc::create($this->limpiar($d));

        return back()->with('padron_ok', 'Registro agregado al padrón.');
    }

    public function update(Request $request, string $ruc)
    {
        $reg = PadronRuc::findOrFail($ruc);
        $d = $request->validate($this->reglas(), $this->mensajes());
        $reg->update($this->limpiar($d));

        return back()->with('padron_ok', 'Registro actualizado.');
    }

    public function destroy(string $ruc)
    {
        PadronRuc::findOrFail($ruc)->delete();

        return back()->with('padron_ok', 'Registro eliminado del padrón.');
    }

    private function reglas(): array
    {
        return [
            'pad_dv' => ['nullable', 'regex:/^\d$/'],
            'pad_nombre' => ['required', 'string', 'max:255'],
            'pad_estado' => ['nullable', Rule::in(self::ESTADOS)],
        ];
    }

    private function mensajes(): array
    {
        return [
            'pad_ruc.regex' => 'El RUC debe tener hasta 12 dígitos (puede terminar en una letra).',
            'pad_ruc.unique' => 'Ese RUC ya está en el padrón: buscalo en el listado y editalo.',
            'pad_dv.regex' => 'El dígito verificador es un solo número.',
            'pad_nombre.required' => 'Escribí el nombre o la razón social.',
        ];
    }

    private function limpiar(array $d): array
    {
        $d['pad_nombre'] = mb_strtoupper(preg_replace('/\s+/', ' ', trim($d['pad_nombre'])));
        $d['pad_dv'] = ($d['pad_dv'] ?? '') === '' ? null : $d['pad_dv'];
        $d['pad_estado'] = ($d['pad_estado'] ?? '') === '' ? null : $d['pad_estado'];

        return $d;
    }
}
