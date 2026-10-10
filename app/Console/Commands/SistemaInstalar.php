<?php

namespace App\Console\Commands;

use App\Models\Rol;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Deja lista una base NUEVA (con las tablas ya creadas): permisos, administrador, una sucursal con su depósito y caja,
 * y opcionalmente datos de ejemplo inventados para una demo. Se puede correr más de una vez sin duplicar nada.
 */
class SistemaInstalar extends Command
{
    protected $signature = 'sistema:instalar
        {--usuario= : Usuario de acceso del administrador}
        {--email= : Correo del administrador}
        {--nombre= : Nombre del administrador}
        {--clave= : Contraseña (solo si no hay terminal; borrá el comando después)}
        {--demo : Carga productos y clientes de ejemplo (todos inventados)}';

    protected $description = 'Prepara una instalación nueva: permisos, administrador, sucursal, depósito, caja y (opcional) datos de ejemplo';

    public function handle(StockService $stock): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('usuarios') || ! \Illuminate\Support\Facades\Schema::hasTable('permisos')) {
            $this->error('Todavía no están las tablas. Primero cargá la estructura de la base y corré php artisan migrate --force.');

            return self::FAILURE;
        }

        Artisan::call('permisos:sincronizar');
        $this->info('Permisos cargados.');

        $nombreAdmin = (string) (config('permisos.roles_admin')[0] ?? 'Administrador');
        $rol = Rol::firstOrCreate(['rol_nombre' => $nombreAdmin], ['rol_descripcion' => 'Acceso total al sistema']);
        $this->info("Rol «{$nombreAdmin}» listo.");

        $this->crearAdministrador($rol);
        $suc = $this->base();

        if ($this->option('demo')) {
            $this->datosDemo($stock, $suc);
        }

        $this->newLine();
        $this->info('Listo. Corré php artisan sistema:verificar para revisar la instalación.');

        return self::SUCCESS;
    }

    private function crearAdministrador(Rol $rol): void
    {
        $hay = User::where('rol_id', $rol->rol_id)->where('usu_activo', true)->exists();
        if ($hay) {
            $this->line('Ya hay un administrador activo: no se crea otro.');

            return;
        }

        $usuario = (string) ($this->option('usuario') ?: $this->ask('Usuario de acceso del administrador', 'admin'));
        $email = (string) ($this->option('email') ?: $this->ask('Correo del administrador'));
        $nombre = (string) ($this->option('nombre') ?: $this->ask('Nombre del administrador', 'Administrador'));

        $claveOpcion = (string) $this->option('clave');
        $clave = $claveOpcion !== '' ? $claveOpcion : (string) $this->secret('Contraseña (mínimo 10 caracteres)');
        if (mb_strlen($clave) < 10) {
            $this->error('La contraseña es muy corta. No se creó el administrador: volvé a correr el comando.');

            return;
        }
        if ($claveOpcion === '' && $clave !== (string) $this->secret('Repetila')) {
            $this->error('Las contraseñas no coinciden. No se creó el administrador: volvé a correr el comando.');

            return;
        }

        User::create([
            'rol_id' => $rol->rol_id,
            'usu_cedula' => '0',
            'usu_nombre' => $nombre,
            'usu_apellido' => 'Administrador',
            'usu_usuario' => $usuario,
            'usu_email' => $email,
            'usu_password' => Hash::make($clave),
            'usu_activo' => true,
        ]);
        $this->info("Administrador «{$usuario}» creado.");
    }

    /** Sucursal, depósito, caja y categoría mínimos para poder vender. Devuelve [suc_id, dep_id, cat_id]. */
    private function base(): array
    {
        $suc = DB::table('sucursales')->orderBy('suc_id')->value('suc_id')
            ?? DB::table('sucursales')->insertGetId(['suc_nombre' => 'Casa central', 'suc_activa' => true], 'suc_id');

        $dep = DB::table('depositos')->where('suc_id', $suc)->orderBy('dep_id')->value('dep_id')
            ?? DB::table('depositos')->insertGetId(['suc_id' => $suc, 'dep_nombre' => 'Depósito principal', 'dep_activo' => true], 'dep_id');

        if (! DB::table('cajas')->where('suc_id', $suc)->exists()) {
            DB::table('cajas')->insert(['suc_id' => $suc, 'caj_nombre' => 'Caja 1', 'caj_activa' => true]);
        }

        $cat = DB::table('categorias')->orderBy('cat_id')->value('cat_id')
            ?? DB::table('categorias')->insertGetId(['cat_nombre' => 'General'], 'cat_id');

        $this->info('Sucursal, depósito, caja y categoría listos.');

        return [$suc, $dep, $cat];
    }

    private function datosDemo(StockService $stock, array $base): void
    {
        [$suc, $dep] = $base;

        if (DB::table('productos')->count() > 0) {
            $this->warn('Ya hay productos cargados: no se agregan los de ejemplo.');

            return;
        }

        $cats = [];
        foreach (['Bebidas', 'Almacén', 'Limpieza', 'Librería'] as $nombre) {
            $cats[$nombre] = DB::table('categorias')->where('cat_nombre', $nombre)->value('cat_id')
                ?? DB::table('categorias')->insertGetId(['cat_nombre' => $nombre], 'cat_id');
        }

        // [código, nombre, categoría, costo, venta, mayorista, stock, mínimo, IVA]
        $productos = [
            ['DEMO-001', 'Agua mineral 500 ml', 'Bebidas', 2500, 4000, 3500, 120, 24, 10],
            ['DEMO-002', 'Gaseosa cola 2 L', 'Bebidas', 9000, 13000, 12000, 60, 12, 10],
            ['DEMO-003', 'Jugo de naranja 1 L', 'Bebidas', 7000, 10500, 9500, 40, 10, 10],
            ['DEMO-004', 'Arroz 1 kg', 'Almacén', 6500, 9000, 8200, 80, 20, 10],
            ['DEMO-005', 'Fideos 500 g', 'Almacén', 3500, 5500, 5000, 90, 20, 10],
            ['DEMO-006', 'Aceite de girasol 900 ml', 'Almacén', 14000, 19500, 18000, 35, 8, 10],
            ['DEMO-007', 'Azúcar 1 kg', 'Almacén', 5000, 7500, 6800, 70, 15, 10],
            ['DEMO-008', 'Yerba mate 1 kg', 'Almacén', 18000, 26000, 24000, 45, 10, 10],
            ['DEMO-009', 'Frutas de estación (kg)', 'Almacén', 6000, 9500, 8500, 25, 6, 5],
            ['DEMO-010', 'Detergente 750 ml', 'Limpieza', 8000, 12500, 11000, 50, 10, 10],
            ['DEMO-011', 'Lavandina 1 L', 'Limpieza', 4500, 7000, 6200, 4, 8, 10],
            ['DEMO-012', 'Cuaderno 100 hojas', 'Librería', 9000, 15000, 13000, 30, 8, 10],
            ['DEMO-013', 'Lapicera azul', 'Librería', 1200, 2500, 2000, 150, 30, 10],
            ['DEMO-014', 'Libro escolar (exento)', 'Librería', 35000, 48000, 45000, 12, 4, 0],
        ];

        foreach ($productos as [$codigo, $nombre, $cat, $costo, $venta, $mayor, $cant, $min, $iva]) {
            $id = DB::table('productos')->insertGetId([
                'cat_id' => $cats[$cat], 'suc_id' => $suc, 'dep_id' => $dep,
                'pro_codigo' => $codigo, 'pro_nombre' => $nombre,
                'pro_preciocosto' => $costo, 'pro_precioventa' => $venta, 'pro_preciomayorista' => $mayor,
                'pro_activo' => true, 'pro_stockactual' => 0, 'pro_stockminimo' => $min, 'pro_tipo_iva' => $iva,
            ], 'pro_id');
            $stock->mover($id, 'CARGA_INICIAL', $cant, 'Datos de ejemplo');
        }

        // Clientes inventados (los RUC son ficticios).
        $clientes = [
            ['Cliente', 'Contado', '0000001-0', null, false, 0],
            ['Marta', 'Benítez', '1234567-1', '0981 111 222', false, 0],
            ['Ferretería', 'San Roque', '80012345-6', '0971 333 444', true, 0],
            ['Carlos', 'Gómez', '2345678-2', '0982 555 666', false, 1500000],
            ['Almacén', 'La Esquina', '80023456-7', '0991 777 888', true, 3000000],
        ];
        foreach ($clientes as [$nom, $ape, $doc, $tel, $mayorista, $limite]) {
            DB::table('clientes')->insert([
                'cli_nombre' => $nom, 'cli_apellido' => $ape, 'cli_ruc_ci' => $doc, 'cli_telefono' => $tel,
                'cli_es_mayorista' => $mayorista, 'cli_limite_credito' => $limite, 'cli_permitir_credito' => $limite > 0,
            ]);
        }

        $this->info('Datos de ejemplo cargados: '.count($productos).' productos y '.count($clientes).' clientes inventados.');
    }
}
