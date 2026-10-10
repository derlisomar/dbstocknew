<?php

namespace App\Http\Controllers\Vendedor;

use App\Http\Controllers\Controller;
use App\Models\Auditoria;
use App\Models\LicenciaPago;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService as Cfg;
use App\Services\ContabilidadService;
use App\Services\LicenciaService;
use App\Services\RespaldoService;
use App\Services\RespaldosVendedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/** Respaldos, historial, solicitudes de demo, recibos, avisos de cobro e instalador del panel del vendedor. */
class ExtrasController extends Controller
{
    // ------------------------------------------------------------------ respaldos

    public function respaldos()
    {
        return view('vendedor.respaldos', [
            'lista' => RespaldosVendedor::listar(),
            'pgsql' => DB::getDriverName() === 'pgsql',
            'dias' => (int) config('respaldos.dias'),
            'hora' => config('respaldos.hora'),
        ]);
    }

    public function crearRespaldo(RespaldoService $respaldos)
    {
        try {
            $r = $respaldos->crear();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo generar el respaldo: '.$e->getMessage());
        }
        AuditoriaService::registrar('VENDEDOR_RESPALDO', null, null, ['por' => 'vendedor', 'archivo' => basename($r['archivo'])]);

        return back()->with('success', 'Respaldo creado: '.basename($r['archivo']).' ('.number_format($r['bytes'] / 1048576, 2).' MB).');
    }

    public function descargarRespaldo(string $nombre)
    {
        $ruta = RespaldosVendedor::ruta($nombre);
        abort_if($ruta === null, 404);
        AuditoriaService::registrar('VENDEDOR_RESPALDO_DESCARGA', null, null, ['por' => 'vendedor', 'archivo' => $nombre]);

        return response()->download($ruta, $nombre);
    }

    // ------------------------------------------------------------------ historial

    public function historial()
    {
        $filas = Auditoria::where('aud_accion', 'like', 'VENDEDOR%')->orderByDesc('aud_id')->simplePaginate(30);

        return view('vendedor.historial', ['filas' => $filas]);
    }

    // ------------------------------------------------------------------ solicitudes de demo

    public function demos()
    {
        $hay = Schema::hasTable('demo_solicitudes');
        $filas = $hay ? DB::table('demo_solicitudes')->orderByDesc('dem_id')->limit(200)->get() : collect();
        $conteo = $hay ? DB::table('demo_solicitudes')->selectRaw('dem_estado, count(*) as n')->groupBy('dem_estado')->pluck('n', 'dem_estado')->all() : [];

        return view('vendedor.demos', ['filas' => $filas, 'conteo' => $conteo, 'hay' => $hay]);
    }

    // ------------------------------------------------------------------ recibo de pago

    public function recibo($id)
    {
        $pago = LicenciaPago::with('plan')->findOrFail($id);

        return view('vendedor.recibo', [
            'pago' => $pago,
            'negocio' => Cfg::get('negocio_nombre') ?: 'Negocio',
            'ruc' => Cfg::get('negocio_ruc'),
            'emisor' => config('landing.empresa'),
        ]);
    }

    // ------------------------------------------------------------------ aviso de cobro

    /** Texto del aviso según el estado de la licencia (para correo y WhatsApp). */
    public static function textoAviso(): string
    {
        $e = LicenciaService::estado();
        $negocio = Cfg::get('negocio_nombre') ?: 'tu negocio';
        $empresa = config('landing.empresa');
        $vence = $e['vence'] ? \Carbon\Carbon::parse($e['vence'])->format('d/m/Y') : null;

        $cuerpo = match (true) {
            $e['estado'] === 'SOLO_LECTURA' => "El servicio de {$negocio} está en modo solo lectura por falta de pago. Regularizalo para volver a registrar operaciones.",
            $e['estado'] === 'GRACIA' => "El plan de {$negocio} venció el {$vence} y está en período de gracia. Renovalo para no interrumpir el servicio.",
            $e['estado'] === 'POR_VENCER' => "El plan de {$negocio} vence el {$vence}. Renovalo para no interrumpir el servicio.",
            default => "Te escribimos por el plan de {$negocio}.".($vence ? " Vence el {$vence}." : ''),
        };

        return "Hola. {$cuerpo} Cualquier duda, respondé este mensaje. Gracias. {$empresa}";
    }

    public function enviarAviso(Request $request)
    {
        $email = Cfg::get('negocio_email');
        if (! filled($email)) {
            return back()->with('error', 'Falta el correo del negocio. Cargalo en la sección Negocio.');
        }

        $texto = self::textoAviso();
        try {
            Mail::raw($texto, function ($m) use ($email) {
                $m->to($email)->subject('Aviso sobre tu plan de dbstock');
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo enviar el correo: '.$e->getMessage());
        }
        AuditoriaService::registrar('VENDEDOR_AVISO_COBRO', null, null, ['por' => 'vendedor', 'a' => $email]);

        return back()->with('success', "Aviso enviado a {$email}.");
    }

    // ------------------------------------------------------------------ instalador

    public function instalar(Request $request)
    {
        $hayAdmin = DB::table('usuarios')->join('roles', 'roles.rol_id', '=', 'usuarios.rol_id')
            ->whereRaw('LOWER(roles.rol_nombre) = ?', ['administrador'])->where('usuarios.usu_activo', true)->exists();

        $reglas = $hayAdmin ? [] : [
            'usuario' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:150'],
            'nombre' => ['required', 'string', 'max:100'],
            'clave' => ['required', 'string', 'min:10', 'max:100'],
        ];
        $d = $request->validate($reglas + ['demo' => ['nullable', 'boolean']], [
            'clave.min' => 'La contraseña del administrador debe tener al menos 10 caracteres.',
        ]);

        try {
            Artisan::call('migrate', ['--force' => true]);
            $salida = trim(Artisan::output());
            $args = ['--no-interaction' => true];
            if (! $hayAdmin) {
                $args += ['--usuario' => $d['usuario'], '--email' => $d['email'], '--nombre' => $d['nombre'], '--clave' => $d['clave']];
            }
            if ($request->boolean('demo')) {
                $args['--demo'] = true;
            }
            Artisan::call('sistema:instalar', $args);
            $salida .= "\n\n".trim(Artisan::output());

            if (Cfg::modulo('contabilidad') && ! ContabilidadService::preparada()) {
                Artisan::call('contabilidad:preparar');
                $salida .= "\n\n".trim(Artisan::output());
            }
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'No se pudo completar la instalación: '.$e->getMessage());
        }

        AuditoriaService::registrar('VENDEDOR_INSTALAR', null, null, ['por' => 'vendedor', 'demo' => $request->boolean('demo')]);

        return redirect()->route('vendedor.herramientas')->with('success', 'Instalación terminada.')->with('salida', $salida);
    }
}
