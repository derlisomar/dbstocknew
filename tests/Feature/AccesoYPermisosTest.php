<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Pruebas de login y permisos. Crean sus propias tablas mínimas en SQLite en memoria,
 * así no dependen de tu base real.   Ejecutar:  php artisan test --filter=AccesoYPermisosTest
 */
class AccesoYPermisosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $t) {
            $t->increments('rol_id');
            $t->string('rol_nombre');
            $t->string('rol_descripcion')->nullable();
        });
        Schema::create('permisos', function (Blueprint $t) {
            $t->increments('perm_id');
            $t->string('perm_codigo');
            $t->string('perm_descripcion')->nullable();
            $t->string('perm_modulo')->nullable();
        });
        Schema::create('rol_permisos', function (Blueprint $t) {
            $t->unsignedInteger('rol_id');
            $t->unsignedInteger('perm_id');
        });
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('usu_id');
            $t->unsignedInteger('rol_id')->nullable();
            $t->string('usu_cedula')->nullable();
            $t->string('usu_nombre')->nullable();
            $t->string('usu_apellido')->nullable();
            $t->string('usu_usuario')->unique();
            $t->string('usu_email')->nullable();
            $t->string('usu_password');
            $t->boolean('usu_activo')->default(true);
            $t->string('remember_token')->nullable();
        });

        // Ruta de prueba protegida solo por permiso
        Route::middleware(['web', 'auth', 'permiso:VENTAS_ANULAR'])->get('/_prueba/anular', fn () => 'ok');
        Route::middleware(['web', 'auth', 'permiso:FINANZAS_VER,CAJA_ABRIR_CERRAR'])->get('/_prueba/alguno', fn () => 'ok');

        RateLimiter::clear('x');
    }

    private function usuario(string $rolNombre, array $codigos = [], array $extra = []): User
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => $rolNombre], 'rol_id');

        foreach ($codigos as $codigo) {
            $permId = DB::table('permisos')->insertGetId(['perm_codigo' => $codigo], 'perm_id');
            DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        }

        return User::create(array_merge([
            'rol_id' => $rolId,
            'usu_usuario' => 'u'.$rolId,
            'usu_email' => 'u'.$rolId.'@correo.com',
            'usu_password' => Hash::make('clave-segura-1'),
            'usu_activo' => true,
        ], $extra));
    }

    // ----------------------------------------------------------------- permisos

    public function test_sin_permiso_recibe_403(): void
    {
        $cajero = $this->usuario('Cajero', ['PDV_USAR']);

        $this->actingAs($cajero)->get('/_prueba/anular')->assertForbidden();
    }

    public function test_con_permiso_pasa(): void
    {
        $gerente = $this->usuario('Gerente', ['VENTAS_ANULAR']);

        $this->actingAs($gerente)->get('/_prueba/anular')->assertOk();
    }

    public function test_el_administrador_pasa_siempre(): void
    {
        $admin = $this->usuario('Administrador', []);

        $this->actingAs($admin)->get('/_prueba/anular')->assertOk();
    }

    public function test_alcanza_con_uno_de_varios_permisos(): void
    {
        $cajero = $this->usuario('Cajero', ['CAJA_ABRIR_CERRAR']);

        $this->actingAs($cajero)->get('/_prueba/alguno')->assertOk();
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/_prueba/anular')->assertRedirect('/login');
    }

    public function test_las_rutas_reales_de_administracion_estan_protegidas(): void
    {
        $cajero = $this->usuario('Cajero', ['PDV_USAR']);

        foreach (['/usuarios', '/roles', '/cotizaciones', '/operaciones/ventas', '/operaciones/reporte-abc', '/finanzas/cierres'] as $ruta) {
            $this->actingAs($cajero)->get($ruta)->assertForbidden();
        }

        $this->actingAs($cajero)->post('/operaciones/ventas/1/anular')->assertForbidden();
    }

    // -------------------------------------------------------------------- login

    public function test_login_con_usuario(): void
    {
        $u = $this->usuario('Cajero');

        $this->post('/login', ['login_input' => $u->usu_usuario, 'password' => 'clave-segura-1'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
    }

    public function test_login_con_correo(): void
    {
        $u = $this->usuario('Cajero');

        $this->post('/login', ['login_input' => $u->usu_email, 'password' => 'clave-segura-1'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
    }

    public function test_usuario_desactivado_no_puede_entrar(): void
    {
        $u = $this->usuario('Cajero', [], ['usu_activo' => false]);

        $this->post('/login', ['login_input' => $u->usu_usuario, 'password' => 'clave-segura-1'])
            ->assertSessionHasErrors('login_input');
        $this->assertGuest();
    }

    public function test_clave_incorrecta_no_entra(): void
    {
        $u = $this->usuario('Cajero');

        $this->post('/login', ['login_input' => $u->usu_usuario, 'password' => 'otra'])
            ->assertSessionHasErrors('login_input');
        $this->assertGuest();
    }

    public function test_se_bloquea_despues_de_5_intentos_fallidos(): void
    {
        $u = $this->usuario('Cajero');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login_input' => $u->usu_usuario, 'password' => 'mal']);
        }

        // Aun con la clave correcta, el 6.º intento queda bloqueado
        $this->post('/login', ['login_input' => $u->usu_usuario, 'password' => 'clave-segura-1'])
            ->assertSessionHasErrors('login_input');
        $this->assertGuest();
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
    }

    // ----------------------------------------------------------- catálogo y rutas

    public function test_el_comando_carga_el_catalogo_y_lo_da_a_todos_los_roles(): void
    {
        $rolA = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero'], 'rol_id');
        $rolB = DB::table('roles')->insertGetId(['rol_nombre' => 'Gerente'], 'rol_id');

        $this->artisan('permisos:sincronizar', ['--dar-todos-a-roles' => true])->assertSuccessful();

        $total = count(config('permisos.catalogo'));
        $this->assertEquals($total, DB::table('permisos')->count());
        $this->assertEquals($total, DB::table('rol_permisos')->where('rol_id', $rolA)->count());
        $this->assertEquals($total, DB::table('rol_permisos')->where('rol_id', $rolB)->count());

        // Correrlo de nuevo no duplica nada
        $this->artisan('permisos:sincronizar', ['--dar-todos-a-roles' => true])->assertSuccessful();
        $this->assertEquals($total, DB::table('permisos')->count());
        $this->assertEquals($total, DB::table('rol_permisos')->where('rol_id', $rolA)->count());
    }

    public function test_todos_los_permisos_usados_en_rutas_existen_en_el_catalogo(): void
    {
        $usados = [];
        foreach (Route::getRoutes() as $ruta) {
            foreach ($ruta->gatherMiddleware() as $mw) {
                if (is_string($mw) && str_starts_with($mw, 'permiso:')) {
                    foreach (explode(',', substr($mw, 8)) as $codigo) {
                        $usados[$codigo] = true;
                    }
                }
            }
        }

        $this->assertNotEmpty($usados);
        $this->assertSame([], array_values(array_diff(array_keys($usados), array_keys(config('permisos.catalogo')))),
            'Hay rutas que piden un permiso que no está en config/permisos.php (¿error de tipeo?)');
    }

    public function test_alguien_sin_ser_admin_no_puede_darse_el_rol_de_administrador(): void
    {
        $gestor = $this->usuario('Gestor', ['USUARIOS_GESTIONAR']);
        $adminRol = DB::table('roles')->insertGetId(['rol_nombre' => 'Administrador'], 'rol_id');

        $this->actingAs($gestor)->post('/usuarios', [
            'usu_nombre' => 'X', 'usu_apellido' => 'Y', 'usu_cedula' => '1', 'usu_usuario' => 'nuevo',
            'usu_email' => 'n@x.com', 'usu_password' => 'abcdef', 'rol_id' => $adminRol,
        ])->assertForbidden();

        // ni renombrar un rol cualquiera a "Administrador"
        $this->actingAs($gestor)->post('/roles', ['rol_nombre' => 'administrador'])->assertForbidden();
    }
}
