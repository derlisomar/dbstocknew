<?php

namespace Tests\Feature;

use App\Models\LicenciaPago;
use App\Models\Plan;
use App\Models\PlanAdicional;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionService as Cfg;
use App\Services\SaludService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 11: login nuevo, planes públicos editables, capacidad flexible y panel del vendedor rediseñado con sus extras.
 * Ejecutar:  php artisan test --filter=Parte11Test
 */
class Parte11Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();
        config(['vendedor.clave_hash' => Hash::make('secreta-1234'), 'landing.activa' => true, 'landing.dominio_web' => '', 'landing.dominio_app' => '']);
    }

    private function vendedor()
    {
        return $this->withSession(['vendedor_hasta' => time() + 3600]);
    }

    private function publicar(string $nombre, array $extra = []): Plan
    {
        $plan = Plan::where('plan_nombre', $nombre)->firstOrFail();
        $plan->update($extra + ['plan_publico' => true]);

        return $plan->fresh();
    }

    // ------------------------------------------------------------------ login

    public function test_el_login_tiene_la_marca_nueva_y_ya_no_tiene_los_botones_sociales(): void
    {
        $r = $this->get('/login')->assertOk();
        $r->assertSee('Software de Gestión para empresas');
        $r->assertSee('Dobi Soluciones Informáticas');
        $r->assertSee('mapa-svg', false);
        $r->assertSee('name="login_input"', false);
        $r->assertSee('name="password"', false);
        $r->assertSee('name="remember"', false);
        $r->assertDontSee('TailAdmin');
        $r->assertDontSee('con Google');
        $r->assertDontSee('con X');
    }

    // ------------------------------------------------------------------ planes sembrados

    public function test_la_migracion_deja_tres_planes_ocultos_y_tres_adicionales_sin_precio(): void
    {
        foreach (['Estándar' => 'BASICA', 'Profesional' => 'COMPLETA', 'Corporativo' => 'COMPLETA'] as $n => $ed) {
            $p = Plan::where('plan_nombre', $n)->first();
            $this->assertNotNull($p, $n);
            $this->assertSame($ed, $p->plan_edicion);
            $this->assertFalse($p->plan_publico);
            $this->assertEquals(0, $p->plan_precio);
        }
        $this->assertTrue(Plan::where('plan_nombre', 'Profesional')->first()->plan_destacado);
        $this->assertSame(3, PlanAdicional::count());
    }

    public function test_la_migracion_es_idempotente(): void
    {
        $m = require database_path('migrations/2026_10_17_000001_parte11_planes_publicos.php');
        $m->up();
        $m->up();
        $this->assertSame(1, Plan::where('plan_nombre', 'Profesional')->count());
        $this->assertSame(3, PlanAdicional::count());
    }

    // ------------------------------------------------------------------ landing

    public function test_sin_planes_publicos_la_web_sigue_mostrando_las_dos_ediciones(): void
    {
        $this->get('/')->assertOk()->assertSee('Dos ediciones')->assertDontSee('Capacidad flexible')->assertDontSee('Probar este plan');
    }

    public function test_con_planes_publicos_la_web_muestra_precios_limites_y_etiqueta(): void
    {
        $this->publicar('Estándar', ['plan_precio' => 150000]);
        $this->publicar('Profesional', ['plan_precio' => 290000]);
        $this->publicar('Corporativo');

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Gs. 150.000', $html);
        $this->assertStringContainsString('Gs. 290.000', $html);
        $this->assertStringContainsString('Recomendado', $html);
        $this->assertStringContainsString('A consultar', $html);        // Corporativo sin precio
        $this->assertStringContainsString('class="pl-med', $html);     // sin precio va en la franja "a medida"
        $this->assertStringContainsString('/mes', $html);
        $this->assertStringNotContainsString('Dos ediciones', $html);
        $this->assertStringNotContainsString('Capacidad flexible', $html);   // los adicionales no tienen precio
        $this->assertStringContainsString('Probar este plan', $html);
        // El plan "a consultar" manda por WhatsApp, no a la demo.
        $this->assertStringContainsString('https://wa.me/', $html);
        $this->assertStringNotContainsString('electrónica', $html);
        $this->assertStringNotContainsString('factura electr', $html);
        $this->assertStringNotContainsString('—', $html);
    }

    public function test_los_adicionales_con_precio_aparecen_y_los_inactivos_no(): void
    {
        $this->publicar('Profesional', ['plan_precio' => 290000]);
        PlanAdicional::where('ada_nombre', 'Usuario adicional')->update(['ada_precio' => 30000]);
        PlanAdicional::where('ada_nombre', 'Sucursal adicional')->update(['ada_precio' => 100000, 'ada_activo' => false]);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Capacidad flexible', $html);
        $this->assertStringContainsString('Usuario adicional', $html);
        $this->assertStringContainsString('Gs. 30.000', $html);
        $this->assertStringNotContainsString('Sucursal adicional', $html);
        $this->assertStringNotContainsString('Punto de venta adicional', $html);    // sin precio
    }

    public function test_los_planes_inactivos_u_ocultos_no_se_muestran(): void
    {
        $this->publicar('Profesional', ['plan_precio' => 290000]);
        $this->publicar('Estándar', ['plan_precio' => 150000, 'plan_activo' => false]);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('Gs. 290.000', $html);
        $this->assertStringNotContainsString('Gs. 150.000', $html);
    }

    public function test_las_caracteristicas_tachadas_y_los_periodos(): void
    {
        $this->publicar('Estándar', ['plan_precio' => 1800000, 'plan_meses' => 12]);

        $html = $this->get('/')->getContent();
        $this->assertStringContainsString('/año', $html);
        $this->assertStringContainsString('class="off"', $html);
        $this->assertStringContainsString('Compras y proveedores', $html);
    }

    public function test_los_textos_de_la_seccion_se_pueden_cambiar(): void
    {
        $this->publicar('Profesional', ['plan_precio' => 290000]);
        Cfg::set(['pub_planes_titulo' => 'Elegí tu plan']);
        $this->get('/')->assertSee('Elegí tu plan');
    }

    // ------------------------------------------------------------------ editor de planes

    public function test_el_vendedor_edita_un_plan_y_lo_publica(): void
    {
        $plan = Plan::where('plan_nombre', 'Profesional')->first();
        $this->vendedor()->put(route('vendedor.planes.editar', $plan->plan_id), [
            'plan_nombre' => 'Profesional', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 350000, 'plan_meses' => 1,
            'plan_max_sucursales' => 2, 'plan_max_cajas' => 4, 'plan_max_usuarios' => 8,
            'plan_orden' => 5, 'plan_etiqueta' => 'Más elegido', 'plan_boton' => 'Empezar', 'plan_publico' => '1', 'plan_destacado' => '1',
            'plan_caracteristicas' => "Una cosa\n-Otra cosa",
        ])->assertRedirect(route('vendedor.planes'));

        $plan->refresh();
        $this->assertTrue($plan->plan_publico);
        $this->assertTrue($plan->plan_destacado);
        $this->assertSame('Más elegido', $plan->plan_etiqueta);
        $this->assertSame(5, (int) $plan->plan_orden);
        $this->assertEquals(350000, $plan->plan_precio);
        $this->assertSame([['texto' => 'Una cosa', 'incluye' => true], ['texto' => 'Otra cosa', 'incluye' => false]], $plan->caracteristicas());

        $this->get('/')->assertSee('Más elegido')->assertSee('Empezar')->assertSee('Gs. 350.000');
    }

    public function test_desmarcar_publico_lo_oculta_de_la_web(): void
    {
        $plan = $this->publicar('Profesional', ['plan_precio' => 290000]);
        $this->vendedor()->put(route('vendedor.planes.editar', $plan->plan_id), [
            'plan_nombre' => 'Profesional', 'plan_edicion' => 'COMPLETA', 'plan_precio' => 290000, 'plan_meses' => 1,
        ])->assertRedirect();

        $this->assertFalse($plan->fresh()->plan_publico);
        $this->get('/')->assertSee('Dos ediciones');
    }

    public function test_guardar_adicionales_crea_edita_y_elimina(): void
    {
        $uno = PlanAdicional::where('ada_nombre', 'Usuario adicional')->first();
        $dos = PlanAdicional::where('ada_nombre', 'Sucursal adicional')->first();

        $this->vendedor()->post(route('vendedor.planes.adicionales'), [
            'pub_planes_titulo' => 'Mi título', 'pub_planes_texto' => 'Mi texto',
            'ad' => [
                $uno->ada_id => ['nombre' => 'Usuario extra', 'precio' => 25000, 'periodo' => 'mes', 'orden' => 1, 'activo' => '1'],
                $dos->ada_id => ['nombre' => 'Sucursal adicional', 'precio' => 1, 'periodo' => 'mes', 'orden' => 3, 'eliminar' => '1'],
            ],
            'nuevo' => ['nombre' => 'Soporte extendido', 'precio' => 80000, 'periodo' => 'mes'],
        ])->assertRedirect(route('vendedor.planes'))->assertSessionHas('success');

        $this->assertSame('Usuario extra', $uno->fresh()->ada_nombre);
        $this->assertTrue($uno->fresh()->ada_activo);
        $this->assertEquals(25000, $uno->fresh()->ada_precio);
        $this->assertNull(PlanAdicional::find($dos->ada_id));
        $this->assertSame(1, PlanAdicional::where('ada_nombre', 'Soporte extendido')->count());
        $this->assertSame('Mi título', Cfg::get('pub_planes_titulo'));
    }

    public function test_validaciones_del_plan(): void
    {
        $this->vendedor()->post(route('vendedor.planes.crear'), ['plan_nombre' => '', 'plan_edicion' => 'X', 'plan_precio' => -1, 'plan_meses' => 1])
            ->assertSessionHasErrors(['plan_nombre', 'plan_edicion', 'plan_precio']);
    }

    // ------------------------------------------------------------------ panel rediseñado

    public function test_todas_las_pantallas_del_panel_se_ven(): void
    {
        foreach (['resumen', 'negocio', 'modulos', 'estructura', 'usuarios', 'planes', 'licencia', 'demos', 'respaldos', 'historial', 'herramientas'] as $ruta) {
            $r = $this->vendedor()->get(route('vendedor.'.$ruta));
            $r->assertOk();
            $r->assertSee('pv-side', false);
            $r->assertSee('Panel del vendedor');
        }
    }

    public function test_el_acceso_del_panel_se_ve_sin_menu_lateral(): void
    {
        $this->get(route('vendedor.login'))->assertOk()->assertSee('Panel del vendedor')->assertDontSee('id="pv-side"', false);
    }

    public function test_el_resumen_muestra_cifras_y_salud(): void
    {
        $this->vendedor()->get(route('vendedor.resumen'))->assertOk()
            ->assertSee('Salud del sistema')->assertSee('Base de datos')->assertSee('Ventas del mes')->assertSee('Preparación del negocio');
    }

    public function test_salud_detecta_la_base_y_el_respaldo_faltante(): void
    {
        config(['respaldos.directorio' => sys_get_temp_dir().'/sin-respaldos-'.uniqid()]);
        $items = SaludService::chequeos();
        $por = collect($items)->keyBy('titulo');
        $this->assertSame('ok', $por['Base de datos']['nivel']);
        $this->assertSame('warn', $por['Respaldos']['nivel']);
        $this->assertContains(SaludService::peor($items), ['warn', 'bad']);
    }

    // ------------------------------------------------------------------ respaldos

    public function test_lista_y_descarga_de_respaldos_con_nombre_valido(): void
    {
        $dir = sys_get_temp_dir().'/resp-'.uniqid();
        File::ensureDirectoryExists($dir);
        config(['respaldos.directorio' => $dir]);
        file_put_contents($dir.'/dbstock_20261010_020000.dump', 'datos');
        file_put_contents($dir.'/secreto.txt', 'no');

        $this->vendedor()->get(route('vendedor.respaldos'))->assertOk()->assertSee('dbstock_20261010_020000.dump')->assertDontSee('secreto.txt');
        $this->vendedor()->get('/'.config('vendedor.ruta').'/respaldos/dbstock_20261010_020000.dump')->assertOk()->assertDownload('dbstock_20261010_020000.dump');
        $this->vendedor()->get('/'.config('vendedor.ruta').'/respaldos/secreto.txt')->assertNotFound();
        $this->vendedor()->get('/'.config('vendedor.ruta').'/respaldos/dbstock_20261010_999999.dump')->assertNotFound();
        $this->vendedor()->get('/'.config('vendedor.ruta').'/respaldos/..%2F.env')->assertNotFound();

        File::deleteDirectory($dir);
    }

    public function test_los_respaldos_exigen_el_acceso_del_vendedor(): void
    {
        $this->get(route('vendedor.respaldos'))->assertRedirect(route('vendedor.login'));
        $this->post(route('vendedor.respaldos.crear'))->assertRedirect(route('vendedor.login'));
        $this->get(route('vendedor.historial'))->assertRedirect(route('vendedor.login'));
        $this->get(route('vendedor.demos'))->assertRedirect(route('vendedor.login'));
        $this->get(route('vendedor.licencia.recibo', 1))->assertRedirect(route('vendedor.login'));
        $this->post(route('vendedor.licencia.aviso'))->assertRedirect(route('vendedor.login'));
        $this->post(route('vendedor.herramientas.instalar'))->assertRedirect(route('vendedor.login'));
        $this->post(route('vendedor.planes.adicionales'))->assertRedirect(route('vendedor.login'));
    }

    public function test_crear_respaldo_sin_postgres_avisa_con_un_error_claro(): void
    {
        $this->vendedor()->post(route('vendedor.respaldos.crear'))->assertSessionHas('error');
    }

    // ------------------------------------------------------------------ historial, demos, recibo

    public function test_el_historial_lista_solo_acciones_del_vendedor(): void
    {
        AuditoriaService::registrar('VENDEDOR_PLAN', 'planes', 1, ['por' => 'vendedor', 'plan' => 'Profesional']);
        AuditoriaService::registrar('VENTA_ANULADA', 'ventas', 9, ['x' => 1]);

        $this->vendedor()->get(route('vendedor.historial'))->assertOk()->assertSee('Plan de pago')->assertSee('Profesional')->assertDontSee('VENTA_ANULADA');
    }

    public function test_las_solicitudes_de_demo_se_listan_con_enlace_de_whatsapp(): void
    {
        DB::table('demo_solicitudes')->insert(['dem_nombre' => 'Marta', 'dem_negocio' => 'Ferretería X', 'dem_email' => 'm@x.com', 'dem_telefono' => '0981123456', 'dem_estado' => 'ACTIVA', 'dem_vence' => now()->addDays(5)]);

        $this->vendedor()->get(route('vendedor.demos'))->assertOk()->assertSee('Ferretería X')->assertSee('m@x.com')->assertSee('https://wa.me/595981123456', false);
    }

    public function test_recibo_de_pago(): void
    {
        Cfg::set(['negocio_nombre' => 'Mi Negocio', 'negocio_ruc' => '80000000-1']);
        $pago = LicenciaPago::create(['lpa_fecha' => '2026-10-10', 'lpa_monto' => 290000, 'lpa_forma' => 'Transferencia', 'lpa_referencia' => 'T-55', 'lpa_hasta' => '2026-11-10', 'lpa_estado' => 'ACTIVO']);

        $this->vendedor()->get(route('vendedor.licencia.recibo', $pago->lpa_id))->assertOk()
            ->assertSee('Recibo de pago')->assertSee('Gs. 290.000')->assertSee('Mi Negocio')->assertSee('80000000-1')->assertSee('T-55')->assertDontSee('ANULADO');

        $pago->update(['lpa_estado' => 'ANULADO', 'lpa_motivo_anulacion' => 'error']);
        $this->vendedor()->get(route('vendedor.licencia.recibo', $pago->lpa_id))->assertSee('ANULADO');
    }

    public function test_la_licencia_ofrece_aviso_y_enlace_al_recibo(): void
    {
        Cfg::set(['negocio_nombre' => 'Mi Negocio', 'negocio_telefono' => '0981 123 456', 'negocio_email' => 'n@x.com']);
        LicenciaPago::create(['lpa_fecha' => '2026-10-10', 'lpa_monto' => 1, 'lpa_hasta' => '2026-11-10', 'lpa_estado' => 'ACTIVO']);

        $this->vendedor()->get(route('vendedor.licencia'))->assertOk()->assertSee('Aviso de cobro')->assertSee('https://wa.me/595981123456', false)->assertSee('Recibo');
    }

    public function test_enviar_aviso_requiere_correo_y_lo_envia(): void
    {
        $this->vendedor()->post(route('vendedor.licencia.aviso'))->assertSessionHas('error');

        config(['mail.default' => 'array']);
        Cfg::set(['negocio_email' => 'n@x.com']);
        $this->vendedor()->post(route('vendedor.licencia.aviso'))->assertSessionHas('success');
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'VENDEDOR_AVISO_COBRO')->count());
    }

    public function test_el_instalador_valida_la_clave_del_administrador(): void
    {
        $this->vendedor()->post(route('vendedor.herramientas.instalar'), ['usuario' => 'admin', 'nombre' => 'A', 'email' => 'a@x.com', 'clave' => 'corta'])
            ->assertSessionHasErrors('clave');
    }
}
