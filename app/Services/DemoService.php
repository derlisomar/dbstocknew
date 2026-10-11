<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Mail\DemoAcceso;
use App\Models\DemoSolicitud;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Crea usuarios de prueba (demo) a pedido de la página pública y les manda el acceso por correo.
 * La clave solo viaja por el correo: si el correo no se puede enviar, no queda ningún usuario creado.
 */
class DemoService
{
    /**
     * @param array{nombre:string,negocio:string,email:string,telefono?:?string} $d
     * @return string 'creada' | 'existente'  (el visitante ve el mismo mensaje en ambos casos)
     *
     * @throws NegocioException cuando la demo está apagada o se llegó al tope
     */
    public function solicitar(array $d, ?string $ip): string
    {
        if (! config('landing.demo_activa')) {
            throw new NegocioException('Las demos están pausadas por ahora. Escribinos y te damos acceso.');
        }

        $this->vencerAntiguas();

        $email = mb_strtolower(trim($d['email']));

        // Ya tiene una demo vigente o es un usuario real: no se crea otra y el visitante no se entera de cuál es el caso,
        // pero el pedido queda anotado en el panel del vendedor para que no pase desapercibido.
        $yaExiste = User::whereRaw('LOWER(usu_email) = ?', [$email])->exists()
            || DemoSolicitud::where('dem_email', $email)->where('dem_estado', 'ACTIVA')->exists();
        if ($yaExiste) {
            $this->anotarRepetida($d, $email, $ip);

            return 'existente';
        }

        $tope = (int) config('landing.max_demos_activas');
        if ($tope > 0 && DemoSolicitud::where('dem_estado', 'ACTIVA')->count() >= $tope) {
            throw new NegocioException('Hoy llegamos al máximo de demos activas. Probá mañana o escribinos.');
        }

        try {
            $vence = $this->crear($d, $email, $ip);
        } catch (\Throwable $e) {
            // El correo (o la base) falló: no queda ningún usuario, pero el pedido SÍ se anota para poder contactar a la persona.
            $this->anotarFallida($d, $email, $ip, $e);
            throw $e;
        }

        $this->avisarAlDueno($d, $email, $vence);

        return 'creada';
    }

    /**
     * Vuelve a intentar una demo cuyo correo falló (por ejemplo después de arreglar la configuración del correo).
     *
     * @throws NegocioException si ya no corresponde reintentar
     */
    public function reintentar(int $id, ?string $ip = null): void
    {
        $fila = DemoSolicitud::findOrFail($id);
        if ($fila->dem_estado !== 'FALLIDA') {
            throw new NegocioException('Esta solicitud no está pendiente de envío.');
        }

        $email = mb_strtolower(trim($fila->dem_email));
        if (User::whereRaw('LOWER(usu_email) = ?', [$email])->exists()) {
            throw new NegocioException('Ese correo ya tiene un usuario en el sistema: no se puede crear otra demo con él.');
        }

        $d = ['nombre' => $fila->dem_nombre, 'negocio' => $fila->dem_negocio, 'email' => $email, 'telefono' => $fila->dem_telefono];

        try {
            $fila->delete();                       // si falla otra vez, se anota de nuevo con el motivo actualizado
            $this->crear($d, $email, $ip ?? $fila->dem_ip);
        } catch (\Throwable $e) {
            $this->anotarFallida($d, $email, $ip ?? $fila->dem_ip, $e);
            throw $e;
        }
    }

    /** Crea el usuario de prueba y su solicitud y manda el acceso por correo. Si el correo falla, se deshace todo. */
    private function crear(array $d, string $email, ?string $ip): \Carbon\CarbonInterface
    {
        $clave = $this->generarClave();
        $dias = max(1, (int) config('landing.dias_demo'));
        $vence = now()->addDays($dias);

        DB::transaction(function () use ($d, $email, $clave, $ip, $vence) {
            $usuario = User::create([
                'rol_id' => $this->rolDemo()->rol_id,
                'usu_cedula' => $this->cedulaLibre(),
                'usu_nombre' => Str::limit(trim($d['nombre']), 100, ''),
                'usu_apellido' => 'Demo',
                'usu_usuario' => $this->usuarioLibre($email),
                'usu_email' => $email,
                'usu_password' => Hash::make($clave),
                'usu_activo' => true,
            ]);

            DemoSolicitud::create([
                'dem_nombre' => Str::limit(trim($d['nombre']), 120, ''),
                'dem_negocio' => Str::limit(trim($d['negocio']), 150, ''),
                'dem_email' => $email,
                'dem_telefono' => isset($d['telefono']) ? Str::limit(trim((string) $d['telefono']), 40, '') : null,
                'usu_id' => $usuario->usu_id,
                'dem_ip' => $ip,
                'dem_estado' => 'ACTIVA',
                'dem_vence' => $vence,
            ]);

            // Si el correo falla, se deshace todo: nadie queda con un usuario cuya clave nunca recibió.
            Mail::to($email)->send(new DemoAcceso(
                nombre: trim($d['nombre']),
                usuario: $usuario->usu_usuario,
                clave: $clave,
                urlLogin: \App\Support\Dominios::urlApp('/login'),
                vence: $vence->locale('es')->translatedFormat('j \d\e F \d\e Y'),
                empresa: (string) config('landing.empresa'),
            ));
        });

        return $vence;
    }

    private function anotarFallida(array $d, string $email, ?string $ip, \Throwable $e): void
    {
        try {
            DemoSolicitud::create([
                'dem_nombre' => Str::limit(trim($d['nombre']), 120, ''),
                'dem_negocio' => Str::limit(trim($d['negocio']), 150, ''),
                'dem_email' => $email,
                'dem_telefono' => isset($d['telefono']) && trim((string) $d['telefono']) !== '' ? Str::limit(trim((string) $d['telefono']), 40, '') : null,
                'dem_ip' => $ip,
                'dem_estado' => 'FALLIDA',
                'dem_detalle' => Str::limit($this->motivo($e), 1000, ''),
            ]);
        } catch (\Throwable $x) {
            Log::warning('No se pudo anotar la demo fallida: '.$x->getMessage());
        }
    }

    private function anotarRepetida(array $d, string $email, ?string $ip): void
    {
        try {
            // Una sola anotación por correo por día, para que insistir no llene la lista.
            if (DemoSolicitud::where('dem_email', $email)->where('dem_estado', 'REPETIDA')->where('dem_creada', '>=', now()->subDay())->exists()) {
                return;
            }

            DemoSolicitud::create([
                'dem_nombre' => Str::limit(trim($d['nombre']), 120, ''),
                'dem_negocio' => Str::limit(trim($d['negocio']), 150, ''),
                'dem_email' => $email,
                'dem_telefono' => isset($d['telefono']) && trim((string) $d['telefono']) !== '' ? Str::limit(trim((string) $d['telefono']), 40, '') : null,
                'dem_ip' => $ip,
                'dem_estado' => 'REPETIDA',
                'dem_detalle' => 'Ese correo ya tiene un usuario o una demo activa: no se creó otra ni se envió ningún correo.',
            ]);
        } catch (\Throwable $x) {
            Log::warning('No se pudo anotar la demo repetida: '.$x->getMessage());
        }
    }

    /** Texto corto y legible del error (sin rutas ni claves). */
    private function motivo(\Throwable $e): string
    {
        $m = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '');

        return $m !== '' ? class_basename($e).': '.$m : class_basename($e);
    }

    /** Le avisa a la empresa que hay una demo nueva (sin la clave). Si falla, la demo igual queda creada. */
    private function avisarAlDueno(array $d, string $email, \Carbon\CarbonInterface $vence): void
    {
        $destino = (string) config('landing.correo_contacto');
        if ($destino === '') {
            return;
        }

        try {
            Mail::to($destino)->send(new \App\Mail\DemoNuevaSolicitud(
                nombre: trim($d['nombre']),
                negocio: trim($d['negocio']),
                email: $email,
                telefono: isset($d['telefono']) && trim((string) $d['telefono']) !== '' ? trim((string) $d['telefono']) : null,
                vence: $vence->locale('es')->translatedFormat('j \d\e F \d\e Y'),
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('No se pudo enviar el aviso de nueva demo: '.$e->getMessage());
        }
    }

    /** Desactiva los usuarios de las demos vencidas. Devuelve cuántas se cerraron. */
    public function vencerAntiguas(): int
    {
        $vencidas = DemoSolicitud::where('dem_estado', 'ACTIVA')->where('dem_vence', '<', now())->get();

        foreach ($vencidas as $s) {
            if ($s->usu_id) {
                User::where('usu_id', $s->usu_id)->update(['usu_activo' => false]);
            }
            $s->update(['dem_estado' => 'VENCIDA']);
        }

        return $vencidas->count();
    }

    private function rolDemo(): Rol
    {
        $nombre = (string) config('landing.rol_demo');
        $rol = Rol::where('rol_nombre', $nombre)->first();
        if ($rol) {
            return $rol;
        }

        $rol = Rol::create(['rol_nombre' => $nombre, 'rol_descripcion' => 'Usuarios de prueba creados desde la página pública']);
        $ids = Permiso::whereIn('perm_codigo', (array) config('landing.permisos_demo'))->pluck('perm_id')->all();
        $rol->permisos()->sync($ids);

        return $rol;
    }

    private function generarClave(): string
    {
        // Sin letras que se confunden (0/O, 1/l/I) para que se pueda tipear desde el correo.
        $alfabeto = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $clave = '';
        for ($i = 0; $i < 10; $i++) {
            $clave .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }

        return $clave;
    }

    private function usuarioLibre(string $email): string
    {
        $base = Str::of(Str::before($email, '@'))->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(14, '')->toString();
        $base = $base !== '' ? $base : 'demo';

        do {
            $candidato = 'demo.'.$base.random_int(10, 99);
        } while (User::where('usu_usuario', $candidato)->exists());

        return $candidato;
    }

    private function cedulaLibre(): string
    {
        do {
            $c = 'DEMO'.random_int(10000000, 99999999);
        } while (User::where('usu_cedula', $c)->exists());

        return $c;
    }
}
