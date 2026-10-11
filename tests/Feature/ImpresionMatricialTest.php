<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venta;
use App\Services\ComprobanteMatricial;
use App\Services\ConfiguracionService as Cfg;
use App\Support\TipoImpresion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Impresión matricial (Epson LX-350 y similares): cada caja usa un solo tipo de comprobante.
 * Ejecutar:  php artisan test --filter=ImpresionMatricialTest
 */
class ImpresionMatricialTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero'], 'rol_id');
        $permId = DB::table('permisos')->insertGetId(['perm_codigo' => 'PDV_USAR'], 'perm_id');
        DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        $this->cajero = User::create(['rol_id' => $rolId, 'usu_nombre' => 'Ana', 'usu_usuario' => 'caja1', 'usu_email' => 'c@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        DB::table('sucursales')->insert([
            'suc_id' => 1, 'suc_nombre' => 'Central', 'suc_direccion' => 'Av. Mcal. López 1234', 'suc_telefono' => '021 555 000', 'suc_actividad_economica' => 'Venta de alimentos',
            'suc_timbrado' => '12345678', 'suc_timbrado_inicio' => '2020-01-01', 'suc_timbrado_fin' => '2099-12-31', 'suc_est_punto_exp' => '001-001', 'suc_factura_secuencia' => 1,
        ]);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'MATRICIAL_SIMPLE']);
        DB::table('caja_sesiones')->insert(['caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA']);
        DB::table('clientes')->insert(['cli_id' => 1, 'cli_nombre' => 'Juan Pérez', 'cli_ruc_ci' => '1234567-8', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0]);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_codigo' => 'A01', 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 6000, 'pro_stockactual' => 50, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
            ['pro_id' => 2, 'cat_id' => 1, 'pro_codigo' => 'L02', 'pro_nombre' => 'Leche entera larga vida en caja de un litro marca muy conocida del país', 'pro_precioventa' => 5000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 3000, 'pro_stockactual' => 50, 'pro_activo' => 1, 'pro_tipo_iva' => 5],
        ]);
        $this->ajustarSecuencias();
        Cfg::set(['negocio_nombre' => 'Dobi Soluciones', 'negocio_ruc' => '80099999-1']);
    }

    protected function cobrar(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->cajero)->postJson('/pdv/store', [
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1,
            'carrito' => [['pro_id' => 1, 'cantidad' => 2], ['pro_id' => 2, 'cantidad' => 1]],
        ]);
    }

    private function tipo(string $t): void
    {
        DB::table('cajas')->update(['caj_tipo_impresion' => $t]);
    }

    // --------------------------------------------------------------- tipos

    public function test_hay_cuatro_tipos_y_dos_son_factura_y_dos_matriciales(): void
    {
        $this->assertSame(['TICKET_SIMPLE', 'TICKET_FACTURA', 'MATRICIAL_SIMPLE', 'MATRICIAL_FACTURA'], TipoImpresion::codigos());
        $this->assertTrue(TipoImpresion::esFactura('TICKET_FACTURA') && TipoImpresion::esFactura('MATRICIAL_FACTURA'));
        $this->assertFalse(TipoImpresion::esFactura('TICKET_SIMPLE') || TipoImpresion::esFactura('MATRICIAL_SIMPLE') || TipoImpresion::esFactura(null));
        $this->assertTrue(TipoImpresion::esMatricial('MATRICIAL_SIMPLE') && TipoImpresion::esMatricial('MATRICIAL_FACTURA'));
        $this->assertFalse(TipoImpresion::esMatricial('TICKET_FACTURA') || TipoImpresion::esMatricial(null));
        $this->assertSame('/pdv/ticket-simple/5', TipoImpresion::ruta(null, 5));
        $this->assertSame('/pdv/matricial-factura/5', TipoImpresion::ruta('MATRICIAL_FACTURA', 5));
    }

    public function test_la_venta_informa_el_tipo_de_impresion_de_la_caja(): void
    {
        foreach (TipoImpresion::codigos() as $t) {
            $this->tipo($t);
            $this->cobrar()->assertOk()->assertJson(['success' => true, 'tipo_impresion' => $t]);
        }
    }

    // -------------------------------------------------------- numeración

    public function test_matricial_factura_numera_y_matricial_comun_no(): void
    {
        $this->tipo('MATRICIAL_SIMPLE');
        $this->cobrar()->assertOk();
        $this->assertNull(DB::table('ventas')->value('vta_nro_factura'));
        $this->assertEquals(1, DB::table('sucursales')->value('suc_factura_secuencia'));

        $this->tipo('MATRICIAL_FACTURA');
        $this->cobrar()->assertOk();
        $this->cobrar()->assertOk();
        $this->assertEquals(['0000001', '0000002'], DB::table('ventas')->whereNotNull('vta_nro_factura')->orderBy('vta_id')->pluck('vta_nro_factura')->all());
        $this->assertEquals('12345678', DB::table('ventas')->whereNotNull('vta_nro_factura')->value('vta_timbrado'));
    }

    public function test_matricial_factura_con_timbrado_vencido_no_vende(): void
    {
        $this->tipo('MATRICIAL_FACTURA');
        DB::table('sucursales')->update(['suc_timbrado_fin' => '2020-12-31']);
        $r = $this->cobrar();
        $r->assertStatus(422);
        $this->assertStringContainsString('VENCIDO', $r->json('message'));
        $this->assertSame(0, DB::table('ventas')->count());
    }

    // -------------------------------------------------------- el texto

    public function test_el_comprobante_comun_cabe_en_80_columnas_y_tiene_los_datos(): void
    {
        $this->cobrar()->assertOk();
        $lineas = ComprobanteMatricial::lineas(Venta::firstOrFail(), false);

        foreach ($lineas as $l) {
            $this->assertLessThanOrEqual(80, mb_strlen($l), $l);
        }
        $t = implode("\n", $lineas);
        foreach (['DOBI SOLUCIONES', 'RUC: 80099999-1', 'Av. Mcal. López 1234', 'CLIENTE   : Juan Pérez', 'CI. / RUC.: 1234567-8', 'A01 Arroz', 'TOTAL: Gs. 25.000', 'ATENDIDO POR: Ana', 'NO VALIDO COMO FACTURA'] as $esperado) {
            $this->assertStringContainsString($esperado, $t, $esperado);
        }
        $this->assertStringNotContainsString('TIMBRADO', $t);
        $this->assertStringNotContainsString('COPIA', $t);
    }

    public function test_los_nombres_largos_pasan_a_la_linea_siguiente_sin_perder_texto(): void
    {
        $this->cobrar()->assertOk();
        $lineas = ComprobanteMatricial::lineas(Venta::firstOrFail(), false);
        $t = preg_replace('/\s+/', ' ', implode(' ', $lineas));
        foreach (['L02 Leche entera larga vida en caja de', 'un litro marca muy conocida del país'] as $trozo) {
            $this->assertStringContainsString($trozo, $t, $trozo);
        }
        foreach ($lineas as $l) {
            $this->assertLessThanOrEqual(80, mb_strlen($l));
        }
    }

    public function test_la_factura_lleva_timbrado_numero_e_iva(): void
    {
        $this->tipo('MATRICIAL_FACTURA');
        $this->cobrar()->assertOk();
        $t = implode("\n", ComprobanteMatricial::lineas(Venta::firstOrFail(), true));

        foreach (['TIMBRADO: 12345678', 'FACTURA NRO: 001-001-0000001', '01/01/2020 AL 31/12/2099', 'TOTAL A PAGAR: Gs. 25.000', 'LIQUIDACION', 'TOTAL IVA:', 'ORIGINAL: CLIENTE - COMPRADOR'] as $esperado) {
            $this->assertStringContainsString($esperado, $t, $esperado);
        }
        // IVA 10% de 20.000 = 1.818; IVA 5% de 5.000 = 238; total IVA 2.056
        $this->assertStringContainsString('1.818', $t);
        $this->assertStringContainsString('238', $t);
        $this->assertStringContainsString('2.056', $t);
        $this->assertStringNotContainsString('NO VALIDO COMO FACTURA', $t);
    }

    // -------------------------------------------------------- pantallas

    public function test_las_dos_hojas_matriciales_se_ven_con_el_papel_continuo(): void
    {
        $this->cobrar()->assertOk();
        $id = DB::table('ventas')->value('vta_id');

        $this->actingAs($this->cajero)->get('/pdv/matricial-simple/'.$id)->assertOk()
            ->assertSee('class="hoja"', false)->assertSee('@page { size: 241mm 140mm', false)->assertSee('DOBI SOLUCIONES');
        $this->actingAs($this->cajero)->get('/pdv/matricial-factura/'.$id)->assertOk()->assertSee('Factura matricial');
        $this->actingAs($this->cajero)->get('/pdv/matricial-simple/99999')->assertNotFound();
    }

    public function test_el_tamano_del_papel_se_cambia_por_configuracion(): void
    {
        config(['impresion.matricial.ancho_mm' => 210, 'impresion.matricial.alto_mm' => 297]);
        $this->cobrar()->assertOk();
        $this->actingAs($this->cajero)->get('/pdv/matricial-simple/'.DB::table('ventas')->value('vta_id'))->assertSee('size: 210mm 297mm', false);
    }

    public function test_un_cajero_no_ve_la_hoja_de_una_venta_ajena(): void
    {
        $this->cobrar()->assertOk();
        $id = DB::table('ventas')->value('vta_id');
        $otro = User::create(['rol_id' => $this->cajero->rol_id, 'usu_usuario' => 'caja2', 'usu_email' => 'c2@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        $this->actingAs($otro)->get('/pdv/matricial-simple/'.$id)->assertForbidden();
        $this->actingAs($otro)->get('/pdv/matricial-factura/'.$id)->assertForbidden();
    }

    public function test_el_ticket_de_siempre_sigue_igual(): void
    {
        $this->tipo('TICKET_SIMPLE');
        $this->cobrar()->assertOk();
        $id = DB::table('ventas')->value('vta_id');
        $this->actingAs($this->cajero)->get('/pdv/ticket-simple/'.$id)->assertOk()->assertSee('72mm', false)->assertDontSee('class="hoja"', false);
    }

    // -------------------------------------------------- reimpresión

    public function test_la_reimpresion_usa_el_formato_de_la_caja(): void
    {
        $admin = $this->crearAdmin();

        $this->tipo('MATRICIAL_SIMPLE');
        $this->cobrar()->assertOk();
        $id = DB::table('ventas')->value('vta_id');
        $this->actingAs($admin)->get(route('operaciones.ventas.ticket', $id))->assertOk()->assertSee('class="hoja"', false)->assertSee('COPIA / REIMPRESION');

        $this->tipo('TICKET_SIMPLE');
        $this->actingAs($admin)->get(route('operaciones.ventas.ticket', $id))->assertOk()->assertDontSee('class="hoja"', false)->assertSee('COPIA / REIMPRESION');
    }

    public function test_la_reimpresion_matricial_de_una_factura_sale_como_factura(): void
    {
        $admin = $this->crearAdmin();
        $this->tipo('MATRICIAL_FACTURA');
        $this->cobrar()->assertOk();
        $id = DB::table('ventas')->value('vta_id');

        $this->actingAs($admin)->get(route('operaciones.ventas.ticket', $id))->assertOk()->assertSee('FACTURA NRO: 001-001-0000001')->assertSee('COPIA / REIMPRESION');
    }

    // ------------------------------------------- configuración de cajas

    public function test_el_vendedor_crea_cajas_con_cada_tipo_y_rechaza_los_invalidos(): void
    {
        config(['vendedor.clave_hash' => Hash::make('secreta-1234')]);
        $vend = $this->withSession(['vendedor_hasta' => time() + 3600]);

        foreach (['MATRICIAL_SIMPLE', 'MATRICIAL_FACTURA'] as $i => $t) {
            $vend->post(route('vendedor.cajas.crear'), ['suc_id' => 1, 'caj_nombre' => 'Caja M'.$i, 'caj_tipo_impresion' => $t])->assertSessionHas('success');
            $this->assertSame($t, DB::table('cajas')->where('caj_nombre', 'Caja M'.$i)->value('caj_tipo_impresion'));
        }
        $vend->post(route('vendedor.cajas.crear'), ['suc_id' => 1, 'caj_nombre' => 'Mala', 'caj_tipo_impresion' => 'LASER'])->assertSessionHasErrors('caj_tipo_impresion');
        $this->assertSame(0, DB::table('cajas')->where('caj_nombre', 'Mala')->count());
    }

    public function test_el_administrador_elige_el_tipo_en_la_pantalla_de_cajas(): void
    {
        $admin = $this->crearAdmin();
        $this->actingAs($admin)->get(route('cajas.index'))->assertOk()
            ->assertSee('Matricial Común', false)->assertSee('Matricial Factura', false);

        $this->actingAs($admin)->put('/cajas/1', ['suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'MATRICIAL_FACTURA'])->assertRedirect();
        $this->assertSame('MATRICIAL_FACTURA', DB::table('cajas')->where('caj_id', 1)->value('caj_tipo_impresion'));
        $this->actingAs($admin)->put('/cajas/1', ['suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'XXX'])->assertSessionHasErrors('caj_tipo_impresion');
    }

    private function crearAdmin(): User
    {
        $rol = DB::table('roles')->insertGetId(['rol_nombre' => 'Administrador'], 'rol_id');

        return User::create(['rol_id' => $rol, 'usu_nombre' => 'Admin', 'usu_usuario' => 'admin', 'usu_email' => 'a@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
    }
}
