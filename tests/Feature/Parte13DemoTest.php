<?php

namespace Tests\Feature;

use App\Mail\DemoAcceso;
use App\Models\DemoSolicitud;
use App\Models\User;
use App\Services\CorreoPrueba;
use App\Services\DemoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Parte 13: las solicitudes de demo nunca pasan en silencio.
 * Ejecutar:  php artisan test --filter=Parte13DemoTest
 */
class Parte13DemoTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();
        config(['vendedor.clave_hash' => Hash::make('secreta-1234'), 'landing.activa' => true, 'landing.dominio_web' => '', 'landing.dominio_app' => '', 'landing.demo_activa' => true, 'landing.dias_demo' => 7, 'landing.max_demos_activas' => 100]);
    }

    private function vendedor()
    {
        return $this->withSession(['vendedor_hasta' => time() + 3600]);
    }

    private function datos(string $email = 'nuevo@x.com'): array
    {
        return ['nombre' => 'Marta', 'negocio' => 'Ferretería X', 'email' => $email, 'telefono' => '0981123456'];
    }

    private function usuarioReal(string $email): void
    {
        User::create(['rol_id' => DB::table('roles')->value('rol_id') ?? 1, 'usu_cedula' => '9', 'usu_nombre' => 'Admin', 'usu_apellido' => 'X',
            'usu_usuario' => 'adminx', 'usu_email' => $email, 'usu_password' => Hash::make('clave-segura-1'), 'usu_activo' => true]);
    }

    public function test_si_el_correo_ya_es_de_un_usuario_queda_anotado_y_no_se_manda_nada(): void
    {
        Mail::fake();
        $this->usuarioReal('yo@x.com');

        $r = app(DemoService::class)->solicitar($this->datos('yo@x.com'), '1.1.1.1');

        $this->assertSame('existente', $r);
        Mail::assertNothingSent();
        $fila = DemoSolicitud::where('dem_email', 'yo@x.com')->first();
        $this->assertSame('REPETIDA', $fila->dem_estado);
        $this->assertStringContainsString('ya tiene un usuario', $fila->dem_detalle);
    }

    public function test_la_repetida_se_anota_una_sola_vez_por_dia(): void
    {
        Mail::fake();
        $this->usuarioReal('yo@x.com');
        $s = app(DemoService::class);
        $s->solicitar($this->datos('yo@x.com'), null);
        $s->solicitar($this->datos('yo@x.com'), null);

        $this->assertSame(1, DemoSolicitud::where('dem_email', 'yo@x.com')->count());
    }

    public function test_si_el_correo_falla_no_queda_usuario_pero_la_solicitud_si_con_el_motivo(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused por el servidor smtp'));

        try {
            app(DemoService::class)->solicitar($this->datos(), null);
            $this->fail('Debía lanzar la excepción');
        } catch (\RuntimeException) {
        }

        $this->assertNull(User::where('usu_email', 'nuevo@x.com')->first());
        $fila = DemoSolicitud::where('dem_email', 'nuevo@x.com')->first();
        $this->assertSame('FALLIDA', $fila->dem_estado);
        $this->assertStringContainsString('Connection refused', $fila->dem_detalle);
    }

    public function test_reintentar_crea_la_demo_y_manda_el_acceso(): void
    {
        DemoSolicitud::create(['dem_nombre' => 'Marta', 'dem_negocio' => 'Ferretería X', 'dem_email' => 'nuevo@x.com', 'dem_estado' => 'FALLIDA', 'dem_detalle' => 'x', 'dem_vence' => now()]);
        Mail::fake();

        app(DemoService::class)->reintentar(DemoSolicitud::first()->dem_id, null);

        Mail::assertSent(DemoAcceso::class, fn ($m) => $m->hasTo('nuevo@x.com'));
        $this->assertSame(1, DemoSolicitud::where('dem_email', 'nuevo@x.com')->count());
        $this->assertSame('ACTIVA', DemoSolicitud::first()->dem_estado);
        $this->assertNotNull(User::where('usu_email', 'nuevo@x.com')->first());
    }

    public function test_reintentar_se_niega_si_no_esta_fallida_o_el_correo_ya_es_de_un_usuario(): void
    {
        Mail::fake();
        $a = DemoSolicitud::create(['dem_nombre' => 'A', 'dem_negocio' => 'N', 'dem_email' => 'a@x.com', 'dem_estado' => 'ACTIVA', 'dem_vence' => now()->addDay()]);
        $this->expectException(\App\Exceptions\NegocioException::class);
        app(DemoService::class)->reintentar($a->dem_id, null);
    }

    public function test_el_panel_muestra_las_solicitudes_con_motivo_y_boton_reintentar(): void
    {
        DemoSolicitud::create(['dem_nombre' => 'Marta', 'dem_negocio' => 'Ferretería X', 'dem_email' => 'm@x.com', 'dem_estado' => 'FALLIDA', 'dem_detalle' => 'Connection refused', 'dem_vence' => now()]);
        DemoSolicitud::create(['dem_nombre' => 'Yo', 'dem_negocio' => 'Mi local', 'dem_email' => 'yo@x.com', 'dem_estado' => 'REPETIDA', 'dem_detalle' => 'Ese correo ya tiene un usuario', 'dem_vence' => now()]);

        $this->vendedor()->get(route('vendedor.demos'))->assertOk()
            ->assertSee('Correo no enviado')->assertSee('Connection refused')->assertSee('Reintentar envío')
            ->assertSee('Ya tenía acceso')->assertSee('Probar correo');
    }

    public function test_la_ruta_de_reintentar_funciona_desde_el_panel(): void
    {
        Mail::fake();
        $f = DemoSolicitud::create(['dem_nombre' => 'Marta', 'dem_negocio' => 'Ferretería X', 'dem_email' => 'm@x.com', 'dem_estado' => 'FALLIDA', 'dem_vence' => now()]);

        $this->vendedor()->post(route('vendedor.demos.reintentar', $f->dem_id))->assertSessionHas('success');
        Mail::assertSent(DemoAcceso::class);
    }

    public function test_probar_correo_avisa_si_el_mailer_es_solo_log(): void
    {
        config(['mail.default' => 'log']);
        $r = CorreoPrueba::enviar('yo@x.com');
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('NO salió de verdad', $r['mensaje']);
    }

    public function test_probar_correo_con_smtp_real_confirma_el_envio_y_explica_los_errores(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $this->assertTrue(CorreoPrueba::enviar('yo@x.com')['ok']);

        $this->vendedor()->post(route('vendedor.herramientas.correo'), ['destino' => 'mal'])->assertSessionHasErrors('destino');
        $this->vendedor()->post(route('vendedor.herramientas.correo'), ['destino' => 'yo@x.com'])->assertSessionHas('success');
    }
}
