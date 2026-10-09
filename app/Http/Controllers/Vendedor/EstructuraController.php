<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Sucursales, cajas, depósitos, cotización y usuarios iniciales de un negocio nuevo. El vendedor no tiene límites de plan. */
class EstructuraController extends Controller
{
    public function index()
    {
        return view('vendedor.estructura', [
            'sucursales' => DB::table('sucursales')->orderBy('suc_id')->get(),
            'cajas' => DB::table('cajas')->orderBy('suc_id')->orderBy('caj_id')->get(),
            'depositos' => DB::table('depositos')->orderBy('dep_id')->get(),
            'cotizacion' => DB::table('cotizaciones')->where('cot_activa', true)->orderByDesc('cot_id')->first(),
        ]);
    }

    public function crearSucursal(Request $request)
    {
        $d = $request->validate([
            'suc_nombre' => ['required', 'string', 'max:100'],
            'suc_direccion' => ['nullable', 'string', 'max:200'],
            'suc_telefono' => ['nullable', 'string', 'max:30'],
            'suc_est_punto_exp' => ['nullable', 'string', 'max:10'],
            'suc_timbrado' => ['nullable', 'string', 'max:20'],
            'suc_timbrado_inicio' => ['nullable', 'date'],
            'suc_timbrado_fin' => ['nullable', 'date', 'after_or_equal:suc_timbrado_inicio'],
        ], ['suc_nombre.required' => 'Escribí el nombre de la sucursal.']);

        $id = DB::table('sucursales')->insertGetId([
            'suc_nombre' => $d['suc_nombre'],
            'suc_direccion' => $d['suc_direccion'] ?? null,
            'suc_telefono' => $d['suc_telefono'] ?? null,
            'suc_activa' => true,
            'suc_est_punto_exp' => $d['suc_est_punto_exp'] ?? '001-001',
            'suc_timbrado' => $d['suc_timbrado'] ?? null,
            'suc_timbrado_inicio' => $d['suc_timbrado_inicio'] ?? null,
            'suc_timbrado_fin' => $d['suc_timbrado_fin'] ?? null,
            'suc_factura_secuencia' => 1,
        ], 'suc_id');

        AuditoriaService::registrar('VENDEDOR_SUCURSAL', 'sucursales', $id, ['por' => 'vendedor', 'nombre' => $d['suc_nombre']]);

        return back()->with('success', 'Sucursal creada.');
    }

    public function estadoSucursal($id)
    {
        return $this->alternar('sucursales', 'suc_id', 'suc_activa', (int) $id, 'Sucursal');
    }

    public function crearCaja(Request $request)
    {
        $d = $request->validate([
            'suc_id' => ['required', 'integer', 'exists:sucursales,suc_id'],
            'caj_nombre' => ['required', 'string', 'max:50'],
            'caj_tipo_impresion' => ['required', 'in:TICKET_SIMPLE,TICKET_FACTURA'],
            'caj_impresora' => ['nullable', 'string', 'max:100'],
        ], ['caj_nombre.required' => 'Escribí el nombre de la caja.', 'suc_id.required' => 'Elegí la sucursal de la caja.']);

        $id = DB::table('cajas')->insertGetId([
            'suc_id' => $d['suc_id'], 'caj_nombre' => $d['caj_nombre'], 'caj_activa' => true,
            'caj_impresora' => $d['caj_impresora'] ?? null, 'caj_tipo_impresion' => $d['caj_tipo_impresion'],
        ], 'caj_id');

        AuditoriaService::registrar('VENDEDOR_CAJA', 'cajas', $id, ['por' => 'vendedor', 'nombre' => $d['caj_nombre']]);

        return back()->with('success', 'Caja creada.');
    }

    public function estadoCaja($id)
    {
        return $this->alternar('cajas', 'caj_id', 'caj_activa', (int) $id, 'Caja');
    }

    public function crearDeposito(Request $request)
    {
        $d = $request->validate([
            'suc_id' => ['required', 'integer', 'exists:sucursales,suc_id'],
            'dep_nombre' => ['required', 'string', 'max:100'],
            'dep_descripcion' => ['nullable', 'string', 'max:200'],
        ], ['dep_nombre.required' => 'Escribí el nombre del depósito.']);

        $id = DB::table('depositos')->insertGetId([
            'suc_id' => $d['suc_id'], 'dep_nombre' => $d['dep_nombre'],
            'dep_descripcion' => $d['dep_descripcion'] ?? null, 'dep_activo' => true,
        ], 'dep_id');

        AuditoriaService::registrar('VENDEDOR_DEPOSITO', 'depositos', $id, ['por' => 'vendedor', 'nombre' => $d['dep_nombre']]);

        return back()->with('success', 'Depósito creado.');
    }

    public function estadoDeposito($id)
    {
        return $this->alternar('depositos', 'dep_id', 'dep_activo', (int) $id, 'Depósito');
    }

    public function cotizacion(Request $request)
    {
        $d = $request->validate([
            'cot_dolar' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'cot_real' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ], ['cot_dolar.required' => 'Indicá el valor del dólar.', 'cot_real.required' => 'Indicá el valor del real.']);

        DB::transaction(function () use ($d) {
            DB::table('cotizaciones')->where('cot_activa', true)->update(['cot_activa' => false]);
            DB::table('cotizaciones')->insert([
                'cot_fecha' => now(config('vendedor.zona'))->toDateString(),
                'cot_dolar' => $d['cot_dolar'], 'cot_real' => $d['cot_real'], 'cot_activa' => true,
            ]);
        });
        AuditoriaService::registrar('VENDEDOR_COTIZACION', 'cotizaciones', null, ['por' => 'vendedor', 'dolar' => $d['cot_dolar'], 'real' => $d['cot_real']]);

        return back()->with('success', 'Cotización actualizada.');
    }

    // ------------------------------------------------------------------ usuarios

    public function usuarios()
    {
        return view('vendedor.usuarios', [
            'usuarios' => DB::table('usuarios')->leftJoin('roles', 'roles.rol_id', '=', 'usuarios.rol_id')
                ->select('usuarios.usu_id', 'usuarios.usu_usuario', 'usuarios.usu_nombre', 'usuarios.usu_apellido', 'usuarios.usu_activo', 'roles.rol_nombre')
                ->orderBy('usuarios.usu_id')->get(),
        ]);
    }

    public function crearAdministrador(Request $request)
    {
        $d = $request->validate([
            'usu_nombre' => ['required', 'string', 'max:100'],
            'usu_apellido' => ['required', 'string', 'max:100'],
            'usu_cedula' => ['required', 'string', 'max:20', 'unique:usuarios,usu_cedula'],
            'usu_usuario' => ['required', 'string', 'max:50', 'unique:usuarios,usu_usuario'],
            'usu_email' => ['required', 'email', 'max:150', 'unique:usuarios,usu_email'],
            'usu_password' => ['required', 'string', 'min:8', 'max:100'],
        ], [
            'usu_password.min' => 'La clave debe tener al menos 8 caracteres.',
            'usu_cedula.unique' => 'Ya existe un usuario con esa cédula.',
            'usu_usuario.unique' => 'Ya existe un usuario con ese nombre de usuario.',
            'usu_email.unique' => 'Ya existe un usuario con ese correo.',
        ]);

        $rolId = DB::table('roles')->whereRaw('LOWER(rol_nombre) = ?', ['administrador'])->value('rol_id')
            ?? DB::table('roles')->insertGetId(['rol_nombre' => 'Administrador', 'rol_descripcion' => 'Acceso total'], 'rol_id');

        $usuario = User::create([
            'rol_id' => $rolId, 'usu_cedula' => $d['usu_cedula'], 'usu_nombre' => $d['usu_nombre'], 'usu_apellido' => $d['usu_apellido'],
            'usu_usuario' => $d['usu_usuario'], 'usu_email' => $d['usu_email'],
            'usu_password' => Hash::make($d['usu_password']), 'usu_activo' => true,
        ]);

        AuditoriaService::registrar('VENDEDOR_ADMIN_CREAR', 'usuarios', $usuario->usu_id, ['por' => 'vendedor', 'usuario' => $d['usu_usuario']]);

        return back()->with('success', 'Administrador creado. Ya puede iniciar sesión con ese usuario.');
    }

    public function restablecerClave(Request $request, $id)
    {
        $d = $request->validate(['clave' => ['required', 'string', 'min:8', 'max:100']], ['clave.min' => 'La clave debe tener al menos 8 caracteres.']);

        $usuario = User::findOrFail($id);
        $usuario->update(['usu_password' => Hash::make($d['clave'])]);
        AuditoriaService::registrar('VENDEDOR_CLAVE_USUARIO', 'usuarios', $usuario->usu_id, ['por' => 'vendedor', 'usuario' => $usuario->usu_usuario]);

        return back()->with('success', "Clave de «{$usuario->usu_usuario}» restablecida.");
    }

    // ------------------------------------------------------------------ interno

    private function alternar(string $tabla, string $pk, string $col, int $id, string $nombre)
    {
        $fila = DB::table($tabla)->where($pk, $id)->first();
        abort_unless($fila, 404);

        $nuevo = ! (bool) $fila->{$col};
        DB::table($tabla)->where($pk, $id)->update([$col => $nuevo]);
        AuditoriaService::registrar('VENDEDOR_'.strtoupper($tabla).'_ESTADO', $tabla, $id, ['por' => 'vendedor', 'activo' => $nuevo]);

        return back()->with('success', "{$nombre} ".($nuevo ? 'activado' : 'desactivado').'.');
    }
}
