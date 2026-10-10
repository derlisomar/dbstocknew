<?php

namespace Tests\Feature;

use App\Mail\DemoAcceso;
use App\Models\DemoSolicitud;
use App\Models\User;
use App\Services\DemoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Parte 9: página pública (landing) y demo por correo.
 * Ejecutar:  php artisan test --filter=Parte9Test
 */
class Parte9Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        foreach (config('landing.permisos_demo') as $codigo) {
            DB::table('permisos')->insert(['perm_codigo' => $codigo]);
        }
        DB::table('permisos')->insert(['perm_codigo' => 'USUARIOS_GESTIONAR']);
        $this->ajustarSecuencias();
        config(['landing.demo_activa' => true, 'landing.max_demos_activas' => 100, 'landing.dias_demo' => 7]);
    }

    private function datos(array $extra = []): array
    {
        return array_merge(['nombre' => 'Marta Benítez', 'negocio' => 'Ferretería San Roque', 'email' => 'marta@ferre.com', 'telefono' => '0981123456'], $extra);
    }

    public function test_la_pagina_publica_carga_sin_iniciar_sesion(): void
    {
        $r = $this->get('/');
        $r->assertOk()->assertSee('dbstock')->assertSee('Pedí tu demo gratis')->assertSee('name="sitio_web"', false);
        $r->assertDontSee('electrónica');
        $r->assertDontSee('factura electr');
    }

    public function test_la_pagina_no_usa_rayas_largas(): void
    {
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('—', $html);
        $this->assertStringNotContainsString('–', $html);
    }

    public function test_pedir_demo_crea_usuario_con_rol_demo_y_manda_correo(): void
    {
        Mail::fake();

        $this->postJson('/demo', $this->datos())->assertOk()->assertJson(['ok' => true]);

        $u = User::where('usu_email', 'marta@ferre.com')->firstOrFail();
        $this->assertTrue((bool) $u->usu_activo);
        $this->assertSame('Demo', $u->rol->rol_nombre);
        $this->assertFalse($u->esAdministrador());
        $this->assertStringStartsWith('demo.', $u->usu_usuario);

        // El rol Demo recibe los permisos de la configuración y nada de gestión de usuarios.
        $this->assertTrue($u->tienePermiso('PDV_USAR'));
        $this->assertFalse($u->tienePermiso('USUARIOS_GESTIONAR'));

        $sol = DemoSolicitud::firstOrFail();
        $this->assertSame('ACTIVA', $sol->dem_estado);
        $this->assertSame($u->usu_id, (int) $sol->usu_id);
        $this->assertTrue($sol->dem_vence->isFuture());

        Mail::assertSent(DemoAcceso::class, function (DemoAcceso $m) use ($u) {
            return $m->hasTo('marta@ferre.com') && $m->usuario === $u->usu_usuario && Hash::check($m->clave, $u->usu_password) && strlen($m->clave) === 10;
        });
    }

    public function test_el_correo_se_ve_bien_y_trae_usuario_y_clave(): void
    {
        $m = new DemoAcceso('Marta', 'demo.marta42', 'Abcd23efGh', 'https://x.com/login', '16 de octubre de 2026', 'Dobi');
        $html = $m->render();
        $this->assertStringContainsString('demo.marta42', $html);
        $this->assertStringContainsString('Abcd23efGh', $html);
        $this->assertStringContainsString('https://x.com/login', $html);
        $this->assertStringNotContainsString('—', $html);
    }

    public function test_si_el_correo_falla_no_queda_ningun_usuario_creado(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $this->postJson('/demo', $this->datos())->assertStatus(500)->assertJsonPath('ok', false);

        $this->assertSame(0, User::where('usu_email', 'marta@ferre.com')->count());
        $this->assertSame(0, DemoSolicitud::count());
    }

    public function test_pedir_dos_veces_con_el_mismo_correo_no_crea_otro_usuario(): void
    {
        Mail::fake();
        $this->postJson('/demo', $this->datos())->assertOk();
        $this->postJson('/demo', $this->datos(['email' => 'MARTA@ferre.com']))->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, User::where('usu_email', 'marta@ferre.com')->count());
        Mail::assertSent(DemoAcceso::class, 1);
    }

    public function test_no_se_puede_pedir_demo_con_el_correo_de_un_usuario_real(): void
    {
        Mail::fake();
        $rol = DB::table('roles')->insertGetId(['rol_nombre' => 'Administrador'], 'rol_id');
        User::create(['rol_id' => $rol, 'usu_usuario' => 'jefe', 'usu_email' => 'jefe@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        $this->postJson('/demo', $this->datos(['email' => 'jefe@x.com']))->assertOk()->assertJson(['ok' => true]);

        Mail::assertNothingSent();
        $this->assertSame(0, DemoSolicitud::count());
    }

    public function test_campo_trampa_de_robots_responde_bien_pero_no_crea_nada(): void
    {
        Mail::fake();
        $this->postJson('/demo', $this->datos(['sitio_web' => 'http://spam.com']))->assertOk();

        Mail::assertNothingSent();
        $this->assertSame(0, User::count());
    }

    public function test_validacion_de_datos(): void
    {
        $r = $this->postJson('/demo', ['nombre' => '', 'negocio' => '', 'email' => 'no-es-correo']);
        $r->assertStatus(422)->assertJsonValidationErrors(['nombre', 'negocio', 'email']);
    }

    public function test_demo_apagada_responde_con_aviso(): void
    {
        config(['landing.demo_activa' => false]);
        $this->postJson('/demo', $this->datos())->assertStatus(409)->assertJsonPath('ok', false);
        $this->get('/')->assertOk()->assertSee('Las demos están pausadas');
    }

    public function test_tope_de_demos_activas(): void
    {
        Mail::fake();
        config(['landing.max_demos_activas' => 1]);
        $this->postJson('/demo', $this->datos())->assertOk();

        $this->postJson('/demo', $this->datos(['email' => 'otro@x.com']))->assertStatus(409);
        $this->assertSame(1, DemoSolicitud::count());
    }

    public function test_demos_vencidas_se_desactivan(): void
    {
        Mail::fake();
        $this->postJson('/demo', $this->datos())->assertOk();
        DemoSolicitud::query()->update(['dem_vence' => now()->subDay()]);

        $this->artisan('demo:limpiar')->assertSuccessful();

        $this->assertFalse((bool) User::where('usu_email', 'marta@ferre.com')->value('usu_activo'));
        $this->assertSame('VENCIDA', DemoSolicitud::first()->dem_estado);

        // Y con el usuario desactivado ya no puede entrar, aunque tenga la clave.
        $this->post('/login', ['login_input' => 'marta@ferre.com', 'password' => 'cualquiera'])->assertSessionHasErrors('login_input');
    }

    public function test_el_usuario_demo_puede_entrar_con_la_clave_del_correo(): void
    {
        $clave = null;
        Mail::fake();
        $this->postJson('/demo', $this->datos())->assertOk();
        Mail::assertSent(DemoAcceso::class, function (DemoAcceso $m) use (&$clave) {
            $clave = $m->clave;

            return true;
        });

        $this->post('/login', ['login_input' => 'marta@ferre.com', 'password' => $clave])->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_limite_de_pedidos_por_conexion(): void
    {
        Mail::fake();
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/demo', $this->datos(['email' => "p{$i}@x.com"]))->assertOk();
        }
        $this->postJson('/demo', $this->datos(['email' => 'p5@x.com']))->assertStatus(429);
    }

    public function test_el_servicio_vence_antiguas_al_recibir_un_pedido_nuevo(): void
    {
        Mail::fake();
        $this->postJson('/demo', $this->datos())->assertOk();
        DemoSolicitud::query()->update(['dem_vence' => now()->subMinute()]);

        $cerradas = app(DemoService::class)->vencerAntiguas();

        $this->assertSame(1, $cerradas);
    }

    public function test_dos_dominios_la_web_muestra_la_landing_y_el_resto_va_al_sistema(): void
    {
        config(['landing.dominio_web' => 'dbstock.test', 'landing.dominio_app' => 'app.dbstock.test', 'app.url' => 'https://app.dbstock.test']);

        $this->get('http://dbstock.test/')->assertOk()->assertSee('https://app.dbstock.test/login', false);
        $this->get('http://www.dbstock.test/')->assertOk();
        $this->get('http://dbstock.test/login')->assertRedirect('https://app.dbstock.test/login');
        $this->get('http://dbstock.test/panel-vendedor/entrar')->assertRedirect('https://app.dbstock.test/panel-vendedor/entrar');
    }

    public function test_dos_dominios_la_raiz_del_sistema_lleva_al_inicio_y_no_acepta_demo(): void
    {
        config(['landing.dominio_web' => 'dbstock.test', 'landing.dominio_app' => 'app.dbstock.test']);

        $this->get('http://app.dbstock.test/')->assertRedirect('/dashboard');
        $this->post('http://app.dbstock.test/demo', $this->datos())->assertNotFound();
        $this->get('http://app.dbstock.test/login')->assertOk();
    }

    public function test_dos_dominios_el_correo_apunta_al_login_del_sistema(): void
    {
        config(['landing.dominio_web' => 'dbstock.test', 'landing.dominio_app' => 'app.dbstock.test', 'app.url' => 'https://app.dbstock.test']);
        Mail::fake();

        $this->postJson('http://dbstock.test/demo', $this->datos())->assertOk();

        Mail::assertSent(DemoAcceso::class, fn ($m) => $m->urlLogin === 'https://app.dbstock.test/login');
    }

    public function test_sin_dominios_configurados_todo_sigue_en_uno_solo(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();
    }

    public function test_instalacion_de_cliente_sin_landing_va_directo_al_sistema(): void
    {
        config(['landing.activa' => false]);

        $this->get('/')->assertRedirect(route('dashboard'));
        $this->postJson('/demo', $this->datos())->assertNotFound();
    }

    public function test_la_demo_esta_excluida_de_la_verificacion_csrf(): void
    {
        // En los tests Laravel salta el CSRF, así que se comprueba la lista de exclusiones.
        $this->assertContains('demo', app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->getExcludedPaths());
        $this->assertNotContains('login', app(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->getExcludedPaths());
    }

    public function test_se_le_avisa_a_la_empresa_sin_la_clave(): void
    {
        Mail::fake();
        config(['landing.correo_contacto' => 'info@dobipy.com']);

        $this->postJson('/demo', $this->datos())->assertOk();

        Mail::assertSent(\App\Mail\DemoNuevaSolicitud::class, fn ($m) => $m->hasTo('info@dobipy.com') && $m->negocio === 'Ferretería San Roque' && ! str_contains(json_encode((array) $m), 'clave'));
        Mail::assertSent(DemoAcceso::class, fn ($m) => $m->hasTo('marta@ferre.com') && $m->hasReplyTo('info@dobipy.com'));
    }

    public function test_si_falla_el_aviso_a_la_empresa_la_demo_igual_se_crea(): void
    {
        config(['landing.correo_contacto' => 'info@dobipy.com']);
        Mail::fake();
        Mail::shouldReceive('to')->andReturnUsing(function ($destino) {
            return new class($destino) {
                public function __construct(private $d) {}
                public function send($m) { if ($m instanceof \App\Mail\DemoNuevaSolicitud) { throw new \RuntimeException('smtp caído'); } }
            };
        });

        $this->postJson('/demo', $this->datos())->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(1, DemoSolicitud::where('dem_estado', 'ACTIVA')->count());
    }

    public function test_la_pagina_muestra_whatsapp_y_correo_de_contacto(): void
    {
        config(['landing.whatsapp' => '595973615297', 'landing.correo_contacto' => 'info@dobipy.com']);

        $this->get('/')->assertOk()
            ->assertSee('https://wa.me/595973615297', false)
            ->assertSee('+595 973 615 297')
            ->assertSee('mailto:info@dobipy.com', false)
            ->assertSee('Sistemas a medida')
            ->assertSee('iOS y Android');
    }

    public function test_perfil_cambia_datos_y_clave_del_propio_usuario(): void
    {
        $u = User::create(['rol_id' => DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero']), 'usu_cedula' => '123', 'usu_nombre' => 'Ana', 'usu_apellido' => 'Paz', 'usu_usuario' => 'ana', 'usu_email' => 'ana@x.com', 'usu_password' => Hash::make('claveVieja1'), 'usu_activo' => true]);

        $this->actingAs($u)->get('/perfil')->assertOk()->assertSee('Mi perfil');
        $this->actingAs($u)->put('/perfil', ['usu_nombre' => 'Ana María', 'usu_apellido' => 'Paz', 'usu_email' => 'ana.maria@x.com'])->assertRedirect(route('perfil.edit'));
        $this->assertSame('ana.maria@x.com', $u->fresh()->usu_email);

        $this->actingAs($u)->put('/perfil/clave', ['clave_actual' => 'incorrecta', 'clave_nueva' => 'nuevaClave9', 'clave_nueva_confirmation' => 'nuevaClave9'])->assertSessionHasErrors('clave_actual');
        $this->assertTrue(Hash::check('claveVieja1', $u->fresh()->usu_password));

        $this->actingAs($u)->put('/perfil/clave', ['clave_actual' => 'claveVieja1', 'clave_nueva' => 'nuevaClave9', 'clave_nueva_confirmation' => 'nuevaClave9'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('nuevaClave9', $u->fresh()->usu_password));
    }

    public function test_perfil_no_deja_usar_el_correo_de_otro_usuario(): void
    {
        $rol = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero']);
        $a = User::create(['rol_id' => $rol, 'usu_cedula' => '1', 'usu_nombre' => 'A', 'usu_usuario' => 'a', 'usu_email' => 'a@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
        User::create(['rol_id' => $rol, 'usu_cedula' => '2', 'usu_nombre' => 'B', 'usu_usuario' => 'b', 'usu_email' => 'b@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        $this->actingAs($a)->put('/perfil', ['usu_nombre' => 'A', 'usu_email' => 'b@x.com'])->assertSessionHasErrors('usu_email');
    }

    public function test_notificaciones_solo_muestran_lo_que_el_rol_puede_ver(): void
    {
        $rol = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero']);
        $u = User::create(['rol_id' => $rol, 'usu_cedula' => '5', 'usu_nombre' => 'C', 'usu_usuario' => 'c', 'usu_email' => 'c@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
        DB::table('cuentas_pagar')->insert(['com_id' => 1, 'prov_id' => 1, 'cpa_monto_total' => 100000, 'cpa_saldo_pendiente' => 100000, 'cpa_fecha_vencimiento' => now()->subDays(3)->toDateString(), 'cpa_estado' => 'PENDIENTE']);

        // Sin permiso de pagos a proveedores no ve nada.
        $this->actingAs($u)->getJson('/notificaciones')->assertOk()->assertJson(['total' => 0]);

        $perm = DB::table('permisos')->insertGetId(['perm_codigo' => 'PAGOS_PROVEEDORES']);
        DB::table('rol_permisos')->insert(['rol_id' => $rol, 'perm_id' => $perm]);

        $r = $this->actingAs(User::find($u->usu_id))->getJson('/notificaciones')->assertOk();
        $this->assertSame(1, $r->json('total'));
        $this->assertStringContainsString('pago a proveedor vencido', $r->json('items.0.titulo'));
    }

    public function test_sin_sesion_no_hay_perfil_ni_notificaciones(): void
    {
        $this->get('/perfil')->assertRedirect('/login');
        $this->getJson('/notificaciones')->assertUnauthorized();
    }
}
