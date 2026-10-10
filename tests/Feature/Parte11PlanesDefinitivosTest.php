<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\PlanAdicional;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Planes y adicionales definitivos (Inicio, Negocio, Crecimiento, Empresa y A medida).
 * Ejecutar:  php artisan test --filter=Parte11PlanesDefinitivosTest
 */
class Parte11PlanesDefinitivosTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();
        config(['vendedor.clave_hash' => Hash::make('secreta-1234'), 'landing.activa' => true, 'landing.dominio_web' => '', 'landing.dominio_app' => '', 'landing.whatsapp' => '595981000000']);
    }

    private function cargar(): void
    {
        (require database_path('migrations/2026_10_18_000001_parte11_planes_definitivos.php'))->up();
    }

    public function test_carga_los_cinco_planes_con_sus_precios_y_limites(): void
    {
        $idsAntes = Plan::whereIn('plan_nombre', ['Estándar', 'Profesional', 'Corporativo'])->pluck('plan_id', 'plan_nombre');
        $this->cargar();

        $esperado = [
            'Inicio' => [150000, 1, 1, 1, 'BASICA'],
            'Negocio' => [220000, 1, 2, 3, 'BASICA'],
            'Crecimiento' => [350000, 2, 4, 6, 'COMPLETA'],
            'Empresa' => [480000, 3, 7, 10, 'COMPLETA'],
            'A medida' => [0, 0, 0, 0, 'COMPLETA'],
        ];
        foreach ($esperado as $nombre => [$precio, $suc, $caj, $usu, $ed]) {
            $p = Plan::where('plan_nombre', $nombre)->first();
            $this->assertNotNull($p, $nombre);
            $this->assertEquals($precio, (float) $p->plan_precio, $nombre);
            $this->assertSame([$suc, $caj, $usu, $ed], [(int) $p->plan_max_sucursales, (int) $p->plan_max_cajas, (int) $p->plan_max_usuarios, $p->plan_edicion], $nombre);
            $this->assertTrue($p->plan_publico && $p->plan_activo, $nombre);
        }
        $this->assertTrue(Plan::where('plan_nombre', 'Crecimiento')->first()->plan_destacado);
        $this->assertSame('Recomendado', Plan::where('plan_nombre', 'Crecimiento')->first()->plan_etiqueta);

        // Los planes de ejemplo se reutilizan: mismo id, nuevo nombre.
        $this->assertSame($idsAntes['Estándar'], Plan::where('plan_nombre', 'Negocio')->value('plan_id'));
        $this->assertSame($idsAntes['Profesional'], Plan::where('plan_nombre', 'Crecimiento')->value('plan_id'));
        $this->assertSame($idsAntes['Corporativo'], Plan::where('plan_nombre', 'A medida')->value('plan_id'));
        $this->assertSame(0, Plan::whereIn('plan_nombre', ['Estándar', 'Profesional', 'Corporativo'])->count());
    }

    public function test_carga_los_tres_adicionales_con_precio(): void
    {
        $this->cargar();
        $this->assertEquals(25000, (float) PlanAdicional::where('ada_nombre', 'Usuario adicional')->value('ada_precio'));
        $this->assertEquals(45000, (float) PlanAdicional::where('ada_nombre', 'Caja adicional')->value('ada_precio'));
        $this->assertEquals(89000, (float) PlanAdicional::where('ada_nombre', 'Sucursal adicional')->value('ada_precio'));
        $this->assertSame(0, PlanAdicional::where('ada_nombre', 'Punto de venta adicional')->count());
        $this->assertSame(3, PlanAdicional::count());
    }

    public function test_es_idempotente(): void
    {
        $this->cargar();
        $planes = Plan::count();
        $this->cargar();
        $this->assertSame($planes, Plan::count());
        $this->assertSame(1, Plan::where('plan_nombre', 'Inicio')->count());
        $this->assertSame(3, PlanAdicional::count());
    }

    public function test_conserva_lo_que_el_vendedor_ya_cambio_en_otros_planes(): void
    {
        Plan::create(['plan_nombre' => 'Mi plan propio', 'plan_edicion' => 'BASICA', 'plan_precio' => 99000, 'plan_meses' => 1, 'plan_max_sucursales' => 1, 'plan_max_cajas' => 1, 'plan_max_usuarios' => 1, 'plan_activo' => true]);
        $this->cargar();
        $this->assertEquals(99000, (float) Plan::where('plan_nombre', 'Mi plan propio')->value('plan_precio'));
        $this->assertFalse((bool) Plan::where('plan_nombre', 'Mi plan propio')->value('plan_publico'));
    }

    public function test_la_web_muestra_cuatro_tarjetas_la_franja_a_medida_y_los_adicionales(): void
    {
        $this->cargar();
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['Inicio', 'Negocio', 'Crecimiento', 'Empresa', 'Gs. 150.000', 'Gs. 220.000', 'Gs. 350.000', 'Gs. 480.000', 'Recomendado'] as $t) {
            $this->assertStringContainsString($t, $html, $t);
        }
        $this->assertSame(4, substr_count($html, 'class="pl rv'));                // cuatro tarjetas con precio
        $this->assertStringContainsString('pl-n4', $html);
        $this->assertStringContainsString('class="pl-med', $html);                  // la franja "A medida"
        $this->assertStringContainsString('A medida', $html);
        $this->assertStringContainsString('A consultar', $html);
        $this->assertStringContainsString('https://wa.me/595981000000', $html);    // el botón consulta por WhatsApp
        $this->assertStringContainsString('Capacidad flexible', $html);
        foreach (['Usuario adicional', 'Caja adicional', 'Sucursal adicional', 'Gs. 25.000', 'Gs. 45.000', 'Gs. 89.000'] as $t) {
            $this->assertStringContainsString($t, $html, $t);
        }
        $this->assertStringContainsString('<dt>Cajas</dt>', $html);
        $this->assertStringNotContainsString('Puntos de venta', $html);
    }

    public function test_la_web_cumple_las_reglas_de_texto(): void
    {
        $this->cargar();
        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('—', $html);                           // sin rayas largas
        $this->assertStringNotContainsStringIgnoringCase('electrónica', $html);
        $this->assertStringNotContainsStringIgnoringCase('factura electr', $html);
        $this->assertStringNotContainsStringIgnoringCase('sifen', $html);
    }

    public function test_el_panel_del_vendedor_lista_los_planes_nuevos(): void
    {
        $this->cargar();
        $this->withSession(['vendedor_hasta' => time() + 3600])->get(route('vendedor.planes'))
            ->assertOk()->assertSee('Crecimiento')->assertSee('A medida')->assertSee('Caja adicional');
    }
}
