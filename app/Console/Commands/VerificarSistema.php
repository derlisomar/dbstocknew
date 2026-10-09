<?php

namespace App\Console\Commands;

use App\Models\Permiso;
use App\Services\RespaldoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lista de control para pasar a producción: revisa la configuración y la base y dice qué falta.
 * Termina con error (código 1) si hay alguna FALLA, para poder usarlo también en un script de despliegue.
 */
class VerificarSistema extends Command
{
    protected $signature = 'sistema:verificar';

    protected $description = 'Revisa que el sistema esté listo para producción (configuración, base, permisos, respaldos)';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $filas = [];

    public function handle(RespaldoService $respaldos): int
    {
        $this->revisarConfiguracion();
        $this->revisarBase();
        $this->revisarAcceso();
        $this->revisarRespaldos($respaldos);
        $this->revisarCarpetas();

        $this->table(['Resultado', 'Control', 'Detalle'], $this->filas);

        $fallas = collect($this->filas)->where(0, 'FALLA')->count();
        $avisos = collect($this->filas)->where(0, 'AVISO')->count();

        if ($fallas > 0) {
            $this->error("{$fallas} falla(s) y {$avisos} aviso(s). Corregí las FALLAS antes de usar el sistema en producción.");

            return self::FAILURE;
        }

        $this->info($avisos > 0 ? "Sin fallas. Hay {$avisos} aviso(s) para revisar." : 'Todo en orden.');

        return self::SUCCESS;
    }

    private function anotar(string $resultado, string $control, string $detalle = ''): void
    {
        $this->filas[] = [$resultado, $control, $detalle];
    }

    private function revisarConfiguracion(): void
    {
        $produccion = app()->environment('production');
        $this->anotar($produccion ? 'OK' : 'FALLA', 'APP_ENV = production', 'Actual: '.app()->environment());

        $this->anotar(config('app.debug') ? 'FALLA' : 'OK', 'APP_DEBUG = false', config('app.debug') ? 'Con debug activo se muestran contraseñas y rutas ante un error.' : '');

        $this->anotar(config('app.key') ? 'OK' : 'FALLA', 'APP_KEY definida');

        $https = str_starts_with((string) config('app.url'), 'https://');
        $this->anotar($https ? 'OK' : 'AVISO', 'APP_URL con https', (string) config('app.url'));

        $this->anotar(config('app.timezone') === 'America/Asuncion' ? 'OK' : 'AVISO', 'Zona horaria de Paraguay', 'Actual: '.config('app.timezone'));

        $this->anotar(config('session.driver') === 'array' ? 'FALLA' : 'OK', 'Sesiones persistentes', 'Driver: '.config('session.driver'));

        if ($https) {
            $seguro = (bool) config('session.secure');
            $this->anotar($seguro ? 'OK' : 'AVISO', 'Cookie de sesión solo por HTTPS', $seguro ? '' : 'Poné SESSION_SECURE_COOKIE=true en .env');
        }

        $cache = app()->configurationIsCached();
        $this->anotar($cache ? 'OK' : 'AVISO', 'Configuración en caché', $cache ? '' : 'Ejecutá: php artisan config:cache');
    }

    private function revisarBase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->anotar('FALLA', 'Conexión a la base de datos', 'No conecta: revisá DB_* en .env');

            return;
        }

        $driver = DB::getDriverName();
        $this->anotar($driver === 'pgsql' ? 'OK' : 'AVISO', 'Base PostgreSQL', 'Driver: '.$driver);

        $faltan = collect(['auditoria', 'devoluciones', 'detalle_devoluciones'])->reject(fn ($t) => Schema::hasTable($t));
        $this->anotar($faltan->isEmpty() ? 'OK' : 'FALLA', 'Tablas de la Parte 2', $faltan->isEmpty() ? '' : 'Faltan: '.$faltan->implode(', ').' (corré la migración de la Parte 2)');

        $parte3 = Schema::hasColumn('cobranzas', 'cob_anulada_por');
        $this->anotar($parte3 ? 'OK' : 'FALLA', 'Columnas de la Parte 3', $parte3 ? '' : 'Corré la migración de la Parte 3');

        $parte4 = Schema::hasTable('stock_movimientos');
        $this->anotar($parte4 ? 'OK' : 'FALLA', 'Historial de stock (Parte 4)', $parte4 ? '' : 'Corré la migración de la Parte 4');

        if ($parte4) {
            $descuadres = app(\App\Services\StockService::class)->descuadres()->count();
            $this->anotar($descuadres === 0 ? 'OK' : 'AVISO', 'Stock coincide con su historial', $descuadres === 0 ? '' : "{$descuadres} producto(s) no coinciden: corré inventario:conciliar");
        }

        $parte5 = Schema::hasTable('compras') && Schema::hasTable('cuentas_pagar') && Schema::hasColumn('caja_movimientos', 'pag_id');
        $this->anotar($parte5 ? 'OK' : 'FALLA', 'Compras y cuentas a pagar (Parte 5)', $parte5 ? '' : 'Corré la migración de la Parte 5');

        $parte6 = Schema::hasTable('presupuestos') && Schema::hasTable('detalle_presupuestos');
        $this->anotar($parte6 ? 'OK' : 'FALLA', 'Presupuestos (Parte 6)', $parte6 ? '' : 'Corré la migración de la Parte 6');

        $parte8 = Schema::hasTable('configuracion_sistema') && Schema::hasTable('planes') && Schema::hasTable('licencia_pagos');
        $this->anotar($parte8 ? 'OK' : 'FALLA', 'Panel del vendedor (Parte 8)', $parte8 ? '' : 'Corré la migración de la Parte 8');
        $this->anotar(config('vendedor.clave_hash') ? 'OK' : 'AVISO', 'Clave del panel del vendedor', config('vendedor.clave_hash') ? '' : 'Falta VENDEDOR_CLAVE_HASH en .env (php artisan vendedor:clave)');

        if ($driver === 'pgsql') {
            $codigoUnico = DB::table('pg_indexes')->where('indexname', 'uq_productos_codigo')->exists();
            $this->anotar($codigoUnico ? 'OK' : 'AVISO', 'Código de producto único', $codigoUnico ? '' : 'Hay códigos repetidos o falta el índice: revisá y volvé a correr la migración de la Parte 4');
        }

        if ($driver === 'pgsql') {
            $indices = DB::table('pg_indexes')->whereIn('indexname', ['uq_caja_sesion_abierta', 'uq_ventas_factura'])->pluck('indexname');
            $this->anotar($indices->count() === 2 ? 'OK' : 'FALLA', 'Índices únicos de caja y facturas', $indices->count() === 2 ? '' : 'Faltan índices: corré la migración de la Parte 2');
        }
    }

    private function revisarAcceso(): void
    {
        if (! Schema::hasTable('permisos') || ! Schema::hasTable('usuarios')) {
            $this->anotar('FALLA', 'Tablas de usuarios y permisos', 'No existen');

            return;
        }

        $catalogo = array_keys(config('permisos.catalogo', []));
        $cargados = Permiso::whereIn('perm_codigo', $catalogo)->pluck('perm_codigo')->all();
        $faltan = array_diff($catalogo, $cargados);
        $this->anotar($faltan === [] ? 'OK' : 'FALLA', 'Permisos sincronizados', $faltan === [] ? '' : 'Faltan: '.implode(', ', $faltan).' (php artisan permisos:sincronizar)');

        $admins = array_map('mb_strtolower', config('permisos.roles_admin', []));
        $hay = DB::table('usuarios')
            ->join('roles', 'roles.rol_id', '=', 'usuarios.rol_id')
            ->where('usuarios.usu_activo', true)
            ->get(['roles.rol_nombre'])
            ->contains(fn ($f) => in_array(mb_strtolower($f->rol_nombre), $admins, true));
        $this->anotar($hay ? 'OK' : 'FALLA', 'Hay un administrador activo', $hay ? '' : 'Sin un administrador nadie podría gestionar el sistema.');
    }

    private function revisarRespaldos(RespaldoService $respaldos): void
    {
        $ultimo = $respaldos->ultimo();

        if ($ultimo === null) {
            $this->anotar('AVISO', 'Respaldo reciente', 'No hay ningún respaldo en '.config('respaldos.directorio'));

            return;
        }

        $horas = (time() - $ultimo) / 3600;
        $this->anotar($horas <= 36 ? 'OK' : 'AVISO', 'Respaldo reciente', sprintf('El último tiene %.0f horas', $horas));
    }

    private function revisarCarpetas(): void
    {
        foreach ([storage_path(), base_path('bootstrap/cache')] as $ruta) {
            $this->anotar(is_writable($ruta) ? 'OK' : 'FALLA', 'Carpeta con escritura', $ruta);
        }
    }
}
