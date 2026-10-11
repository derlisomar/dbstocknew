<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use App\Services\RolesBase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 12: roles listos para usar.
 * Ejecutar:  php artisan test --filter=Parte12RolesTest
 */
class Parte12RolesTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();
    }

    private function usuarioDe(string $rol): User
    {
        return User::create([
            'rol_id' => Rol::where('rol_nombre', $rol)->value('rol_id'), 'usu_cedula' => '1', 'usu_nombre' => $rol, 'usu_apellido' => 'Prueba',
            'usu_usuario' => strtolower(str_replace(' ', '_', $rol)), 'usu_email' => strtolower(str_replace(' ', '', $rol)).'@x.com',
            'usu_password' => Hash::make('clave-segura-1'), 'usu_activo' => true,
        ]);
    }

    private function codigos(string $rol): array
    {
        return Rol::where('rol_nombre', $rol)->firstOrFail()->permisos()->pluck('perm_codigo')->sort()->values()->all();
    }

    public function test_crea_los_cinco_roles_con_sus_permisos(): void
    {
        $r = RolesBase::preparar();
        $this->assertEqualsCanonicalizing(['Gerente', 'Cajero', 'Vendedor', 'Encargado de stock', 'Contador'], $r['creados']);

        $this->assertSame(['CLIENTES_GESTIONAR', 'PRESUPUESTOS_GESTIONAR'], $this->codigos('Vendedor'));
        $this->assertContains('PDV_USAR', $this->codigos('Cajero'));
        $this->assertContains('CAJA_ABRIR_CERRAR', $this->codigos('Cajero'));
        $this->assertContains('CONTABILIDAD_VER', $this->codigos('Contador'));
        $this->assertSame(['CATALOGO_GESTIONAR', 'COMPRAS_REGISTRAR', 'STOCK_AJUSTAR'], $this->codigos('Encargado de stock'));
    }

    public function test_el_gerente_tiene_todo_menos_administrar_usuarios_configuracion_y_contabilidad_gestionar(): void
    {
        RolesBase::preparar();
        $g = $this->codigos('Gerente');
        $todos = array_keys(config('permisos.catalogo'));
        $this->assertEqualsCanonicalizing(array_values(array_diff($todos, ['USUARIOS_GESTIONAR', 'CONFIG_GESTIONAR', 'CONTABILIDAD_GESTIONAR'])), $g);
        foreach (['VENTAS_ANULAR', 'VENTAS_DEVOLVER', 'CAJA_OPERAR_AJENA', 'AUDITORIA_VER', 'REPORTES_VER', 'CLIENTES_CREDITO'] as $c) {
            $this->assertContains($c, $g, $c);
        }
    }

    public function test_el_cajero_no_anula_ni_devuelve_ni_ve_el_resto(): void
    {
        RolesBase::preparar();
        $c = $this->usuarioDe('Cajero');
        foreach (['PDV_USAR', 'CAJA_ABRIR_CERRAR', 'COBRANZAS_REGISTRAR', 'CAJA_INGRESO_EGRESO'] as $p) {
            $this->assertTrue($c->tienePermiso($p), $p);
        }
        foreach (['VENTAS_ANULAR', 'VENTAS_DEVOLVER', 'COBRANZAS_ANULAR', 'CAJA_TRANSFERIR', 'CAJA_OPERAR_AJENA', 'FINANZAS_VER', 'USUARIOS_GESTIONAR', 'CONTABILIDAD_VER', 'REPORTES_VER', 'CLIENTES_CREDITO'] as $p) {
            $this->assertFalse($c->tienePermiso($p), $p);
        }
    }

    public function test_el_vendedor_no_maneja_dinero_y_no_entra_a_pantallas_ajenas(): void
    {
        RolesBase::preparar();
        $v = $this->usuarioDe('Vendedor');
        $this->assertTrue($v->tienePermiso('PRESUPUESTOS_GESTIONAR'));
        $this->assertFalse($v->tienePermiso('PDV_USAR'));
        $this->assertFalse($v->tienePermiso('CAJA_ABRIR_CERRAR'));

        foreach (['/usuarios', '/finanzas', '/pdv', '/roles'] as $ruta) {
            $this->actingAs($v)->get($ruta)->assertForbidden();
        }
    }

    public function test_ningun_rol_base_recibe_permisos_inexistentes_y_el_administrador_no_se_toca(): void
    {
        RolesBase::preparar();
        $catalogo = array_keys(config('permisos.catalogo'));
        foreach (array_keys(config('permisos.roles_base')) as $rol) {
            $this->assertSame([], array_values(array_diff($this->codigos($rol), $catalogo)), $rol);
        }
        $this->assertSame(0, Rol::where('rol_nombre', 'Administrador')->count());   // no lo crea: lo hace sistema:instalar
        $this->assertSame(count($catalogo), Permiso::count());
    }

    public function test_es_idempotente_y_no_pisa_roles_que_ya_existen(): void
    {
        $propio = Rol::create(['rol_nombre' => 'cajero', 'rol_descripcion' => 'Mío']);       // otro uso de mayúsculas
        $propio->permisos()->sync(Permiso::firstOrCreate(['perm_codigo' => 'REPORTES_VER'], ['perm_descripcion' => 'x', 'perm_modulo' => 'REPORTES'])->perm_id);

        $r = RolesBase::preparar();
        $this->assertContains('Cajero', $r['existentes']);
        $this->assertSame(1, Rol::whereRaw('LOWER(rol_nombre) = ?', ['cajero'])->count());
        $this->assertSame(['REPORTES_VER'], $propio->permisos()->pluck('perm_codigo')->all());   // sigue igual

        $total = Rol::count();
        RolesBase::preparar();
        $this->assertSame($total, Rol::count());
    }

    public function test_restablecer_vuelve_a_los_permisos_originales_pero_nunca_toca_al_administrador(): void
    {
        RolesBase::preparar();
        $v = Rol::where('rol_nombre', 'Vendedor')->first();
        $v->permisos()->sync(Permiso::where('perm_codigo', 'PDV_USAR')->pluck('perm_id')->all());
        $admin = Rol::create(['rol_nombre' => 'Administrador']);

        $r = RolesBase::preparar(true);
        $this->assertContains('Vendedor', $r['restablecidos']);
        $this->assertSame(['CLIENTES_GESTIONAR', 'PRESUPUESTOS_GESTIONAR'], $this->codigos('Vendedor'));
        $this->assertSame(0, $admin->permisos()->count());
    }

    public function test_el_comando_funciona_y_pide_confirmacion_para_restablecer(): void
    {
        $this->artisan('roles:preparar')->assertSuccessful()->expectsOutputToContain('Vendedor: creado');
        $this->artisan('roles:preparar')->assertSuccessful()->expectsOutputToContain('ya existía');

        Rol::where('rol_nombre', 'Vendedor')->first()->permisos()->detach();
        $this->artisan('roles:preparar', ['--restablecer' => true])->expectsConfirmation('Esto pisa los permisos de los roles base que ya existan. ¿Continuar?', 'no')->assertSuccessful();
        $this->assertSame([], $this->codigos('Vendedor'));
        $this->artisan('roles:preparar', ['--restablecer' => true])->expectsConfirmation('Esto pisa los permisos de los roles base que ya existan. ¿Continuar?', 'yes')->assertSuccessful();
        $this->assertSame(['CLIENTES_GESTIONAR', 'PRESUPUESTOS_GESTIONAR'], $this->codigos('Vendedor'));
    }

    public function test_la_migracion_y_el_instalador_dejan_los_roles(): void
    {
        (require database_path('migrations/2026_10_19_000001_parte12_roles_base.php'))->up();
        $this->assertSame(5, Rol::count());

        $this->artisan('sistema:instalar', ['--usuario' => 'dueno', '--email' => 'dueno@dobipy.com', '--nombre' => 'Dueño', '--clave' => 'clave-muy-segura-1'])->assertSuccessful();
        $this->assertSame(6, Rol::count());   // + Administrador
    }
}
