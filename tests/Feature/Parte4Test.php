<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 4: historial de stock, productos validados, reimpresión de tickets, reporte ABC y panel por permisos.
 * Ejecutar:  php artisan test --filter=Parte4Test
 */
class Parte4Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $cajero;
    private User $supervisor;
    private User $bodeguero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        $this->cajero = $this->crearUsuario('caja1', ['PDV_USAR', 'CAJA_ABRIR_CERRAR', 'VENTAS_ANULAR', 'VENTAS_DEVOLVER'], 'Cajero');
        $this->supervisor = $this->crearUsuario('super', ['VENTAS_HISTORIAL', 'REPORTES_VER', 'CATALOGO_GESTIONAR', 'VENTAS_ANULAR', 'VENTAS_DEVOLVER'], 'Supervisor');
        $this->bodeguero = $this->crearUsuario('bodega', ['CATALOGO_GESTIONAR', 'STOCK_AJUSTAR'], 'Bodega');

        DB::table('sucursales')->insert([
            'suc_id' => 1, 'suc_nombre' => 'Central', 'suc_timbrado' => '12345678',
            'suc_timbrado_inicio' => '2020-01-01', 'suc_timbrado_fin' => '2099-12-31', 'suc_factura_secuencia' => 1,
        ]);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE']);
        DB::table('caja_sesiones')->insert([
            'ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA', 'ses_monto_inicial_gs' => 100000,
        ]);
        DB::table('clientes')->insert(['cli_id' => 1, 'cli_nombre' => 'Consumidor', 'cli_ruc_ci' => '1', 'cli_permitir_credito' => 0, 'cli_limite_credito' => 0]);
        DB::table('categorias')->insert(['cat_id' => 1, 'cat_nombre' => 'General']);
        DB::table('depositos')->insert(['dep_id' => 1, 'dep_nombre' => 'Principal']);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_codigo' => 'A1', 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciocosto' => 6000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
        ]);
        DB::table('cotizaciones')->insert(['cot_dolar' => 7500, 'cot_real' => 1500, 'cot_activa' => 1]);

        $this->ajustarSecuencias();

        // Como al instalar en un sistema que ya tenía productos: la migración toma la foto del stock.
        (require database_path('migrations/2026_10_11_000001_parte4_stock_movimientos.php'))->up();
    }

    // ------------------------------------------------------------------ ayudas

    private function crearUsuario(string $login, array $permisos, string $rol): User
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => $rol], 'rol_id');
        foreach ($permisos as $codigo) {
            $permId = DB::table('permisos')->where('perm_codigo', $codigo)->value('perm_id')
                ?? DB::table('permisos')->insertGetId(['perm_codigo' => $codigo], 'perm_id');
            DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        }

        return User::create([
            'rol_id' => $rolId, 'usu_usuario' => $login, 'usu_email' => "$login@x.com",
            'usu_password' => Hash::make('x'), 'usu_activo' => true,
        ]);
    }

    private function vender(int $cantidad = 2): int
    {
        $r = $this->actingAs($this->cajero)->postJson('/pdv/store', [
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1,
            'carrito' => [['pro_id' => 1, 'cantidad' => $cantidad]],
        ]);
        $r->assertOk();

        return (int) $r->json('venta_id');
    }

    private function stock(int $proId = 1): float
    {
        return (float) DB::table('productos')->where('pro_id', $proId)->value('pro_stockactual');
    }

    private function movs(int $proId = 1)
    {
        return DB::table('stock_movimientos')->where('pro_id', $proId)->orderBy('smo_id')->get();
    }

    private function datosProducto(array $extra = []): array
    {
        return array_merge([
            'pro_codigo' => 'N-100', 'pro_nombre' => 'Fideos', 'cat_id' => 1, 'suc_id' => 1, 'dep_id' => 1,
            'pro_preciocosto' => 3000, 'pro_precioventa' => 5000, 'pro_preciomayorista' => 4500,
            'pro_stockminimo' => 5, 'pro_stockactual' => 20, 'pro_tipo_iva' => 10, 'pro_activo' => 1,
        ], $extra);
    }

    /** Venta directa en la base, para probar reportes. */
    private function ventaSql(int $proId, float $cant, float $precio, float $costo): void
    {
        $vta = DB::table('ventas')->insertGetId([
            'suc_id' => 1, 'cli_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_id' => 1, 'vta_fecha' => now(),
            'vta_tipo' => 'CONTADO', 'vta_formapago' => 'EFECTIVO', 'vta_total' => $cant * $precio, 'vta_estado' => 'CONFIRMADA',
        ], 'vta_id');
        DB::table('detalle_ventas')->insert([
            'vta_id' => $vta, 'pro_id' => $proId, 'det_cantidad' => $cant, 'det_preciounitario' => $precio,
            'det_subtotal' => $cant * $precio, 'det_preciocosto' => $costo,
        ]);
    }

    // ------------------------------------------------------------------ historial de stock

    public function test_la_migracion_toma_el_saldo_inicial_y_se_puede_correr_dos_veces(): void
    {
        DB::table('stock_movimientos')->delete();
        $m = require database_path('migrations/2026_10_11_000001_parte4_stock_movimientos.php');
        $m->up();
        $m->up();

        $movs = $this->movs();
        $this->assertCount(1, $movs);
        $this->assertSame('SALDO_INICIAL', $movs[0]->smo_tipo);
        $this->assertEquals(10, $movs[0]->smo_cantidad);
        $this->assertCount(0, app(StockService::class)->descuadres());
    }

    public function test_venta_anulacion_y_devolucion_dejan_movimientos_y_el_stock_cuadra(): void
    {
        $vta = $this->vender(4);
        $this->assertEquals(6, $this->stock());

        $this->actingAs($this->supervisor)->post("/operaciones/ventas/$vta/devolver", [
            'items' => [DB::table('detalle_ventas')->where('vta_id', $vta)->value('det_vta_id') => 1], 'motivo' => 'Roto',
        ]);
        $this->assertEquals(7, $this->stock());

        $this->actingAs($this->supervisor)->post("/operaciones/ventas/$vta/anular", ['motivo' => 'Error de carga'])->assertSessionHas('success');
        $this->assertEquals(10, $this->stock());

        $tipos = $this->movs()->pluck('smo_tipo')->all();
        $this->assertSame(['VENTA', 'DEVOLUCION', 'ANULACION_VENTA'], array_slice($tipos, -3));
        $venta = $this->movs()->firstWhere('smo_tipo', 'VENTA');
        $this->assertEquals(-4, $venta->smo_cantidad);
        $this->assertEquals(6, $venta->smo_stock_resultante);
        $this->assertSame("venta:$vta", $venta->smo_referencia);
        $this->assertEquals($this->cajero->usu_id, $venta->usu_id);
        $this->assertCount(0, app(StockService::class)->descuadres());
    }

    public function test_el_stock_no_puede_quedar_negativo(): void
    {
        $this->actingAs($this->bodeguero);
        $this->expectException(\App\Exceptions\NegocioException::class);
        app(StockService::class)->mover(1, 'AJUSTE_SALIDA', -50, 'prueba');
    }

    public function test_un_movimiento_rechazado_no_cambia_nada(): void
    {
        try {
            app(StockService::class)->mover(1, 'AJUSTE_SALIDA', -50, 'prueba');
        } catch (\Throwable) {
        }
        $this->assertEquals(10, $this->stock());
        $this->assertCount(1, $this->movs());
    }

    public function test_ajustes_manuales_ingreso_salida_y_conteo(): void
    {
        $this->actingAs($this->bodeguero);

        $this->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'INGRESO_MERCADERIA', 'cantidad' => 5, 'motivo' => 'Factura 123'])->assertSessionHas('success');
        $this->assertEquals(15, $this->stock());

        $this->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'AJUSTE_SALIDA', 'cantidad' => 3, 'motivo' => 'Vencido'])->assertSessionHas('success');
        $this->assertEquals(12, $this->stock());

        // Conteo: en el estante hay 9, el sistema dice 12 -> queda un movimiento de -3.
        $this->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'CONTEO', 'cantidad' => 9, 'motivo' => 'Conteo mensual'])->assertSessionHas('success');
        $this->assertEquals(9, $this->stock());
        $ultimo = $this->movs()->last();
        $this->assertSame('CONTEO', $ultimo->smo_tipo);
        $this->assertEquals(-3, $ultimo->smo_cantidad);
        $this->assertStringContainsString('contado: 9', $ultimo->smo_motivo);

        $this->assertSame(3, DB::table('auditoria')->where('aud_accion', 'like', 'STOCK_%')->count());
        $this->assertCount(0, app(StockService::class)->descuadres());
    }

    public function test_ajuste_sin_motivo_o_mayor_al_stock_es_rechazado(): void
    {
        $this->actingAs($this->bodeguero);

        $this->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'AJUSTE_ENTRADA', 'cantidad' => 5, 'motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'AJUSTE_SALIDA', 'cantidad' => 99, 'motivo' => 'x'])->assertSessionHas('error');
        $this->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'VENTA', 'cantidad' => 1, 'motivo' => 'x'])->assertSessionHasErrors('tipo');
        $this->assertEquals(10, $this->stock());
    }

    public function test_solo_con_permiso_se_ajusta_el_stock_y_se_ve_el_inventario(): void
    {
        $this->actingAs($this->cajero)->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'AJUSTE_ENTRADA', 'cantidad' => 5, 'motivo' => 'x'])->assertForbidden();
        $this->actingAs($this->cajero)->get('/inventario')->assertForbidden();
        $this->assertEquals(10, $this->stock());

        // Con solo CATALOGO_GESTIONAR se mira pero no se ajusta.
        $this->actingAs($this->supervisor)->get('/inventario')->assertOk();
        $this->actingAs($this->supervisor)->post('/inventario/movimiento', ['pro_id' => 1, 'tipo' => 'AJUSTE_ENTRADA', 'cantidad' => 5, 'motivo' => 'x'])->assertForbidden();

        $this->actingAs($this->bodeguero)->get('/inventario')->assertOk()->assertSee('Registrar movimiento');
    }

    public function test_el_inventario_filtra_por_tipo_y_avisa_descuadres(): void
    {
        $this->vender(1);
        $this->actingAs($this->bodeguero);

        $r = $this->get('/inventario?tipo=VENTA')->assertOk();
        $this->assertSame(1, substr_count($r->getContent(), 'mov-fila'));

        DB::table('productos')->where('pro_id', 1)->update(['pro_stockactual' => 100]);
        $this->get('/inventario')->assertSee('no coincide con su historial');
    }

    public function test_el_comando_conciliar_detecta_cambios_hechos_directo_en_la_base(): void
    {
        $this->assertSame(0, Artisan::call('inventario:conciliar'));

        DB::table('productos')->where('pro_id', 1)->update(['pro_stockactual' => 77]);
        $this->assertSame(1, Artisan::call('inventario:conciliar'));
        $this->assertStringContainsString('Arroz', Artisan::output());
    }

    public function test_el_verificador_avisa_si_el_stock_no_cuadra(): void
    {
        DB::table('productos')->where('pro_id', 1)->update(['pro_stockactual' => 77]);
        Artisan::call('sistema:verificar');
        $salida = Artisan::output();
        $this->assertStringContainsString('Historial de stock', $salida);
        $this->assertStringContainsString('no coinciden', $salida);
    }

    // ------------------------------------------------------------------ productos

    public function test_crear_producto_valido_con_stock_inicial_deja_carga_inicial(): void
    {
        $this->actingAs($this->supervisor)->post('/productos', $this->datosProducto())->assertSessionHasNoErrors()->assertSessionHas('success');

        $p = DB::table('productos')->where('pro_codigo', 'N-100')->first();
        $this->assertNotNull($p);
        $this->assertEquals(20, $p->pro_stockactual);
        $m = $this->movs($p->pro_id);
        $this->assertCount(1, $m);
        $this->assertSame('CARGA_INICIAL', $m[0]->smo_tipo);
        $this->assertCount(0, app(StockService::class)->descuadres());
    }

    public function test_producto_con_datos_invalidos_no_se_guarda(): void
    {
        $this->actingAs($this->supervisor);
        $antes = DB::table('productos')->count();

        $this->post('/productos', $this->datosProducto(['pro_codigo' => 'A1']))->assertSessionHasErrors('pro_codigo');      // repetido
        $this->post('/productos', $this->datosProducto(['pro_precioventa' => -5]))->assertSessionHasErrors('pro_precioventa');
        $this->post('/productos', $this->datosProducto(['cat_id' => 999]))->assertSessionHasErrors('cat_id');
        $this->post('/productos', $this->datosProducto(['pro_tipo_iva' => 7]))->assertSessionHasErrors('pro_tipo_iva');
        $this->post('/productos', $this->datosProducto(['pro_nombre' => '']))->assertSessionHasErrors('pro_nombre');
        $this->post('/productos', $this->datosProducto(['pro_stockactual' => -3]))->assertSessionHasErrors('pro_stockactual');

        $this->assertSame($antes, DB::table('productos')->count());
    }

    public function test_editar_producto_no_cambia_el_stock_ni_se_cuela_otro_campo(): void
    {
        $this->actingAs($this->supervisor)->put('/productos/1', $this->datosProducto([
            'pro_codigo' => 'A1', 'pro_nombre' => 'Arroz premium', 'pro_stockactual' => 9999, 'pro_precioventa' => 11000,
        ]))->assertSessionHasNoErrors();

        $p = DB::table('productos')->where('pro_id', 1)->first();
        $this->assertSame('Arroz premium', $p->pro_nombre);
        $this->assertEquals(11000, $p->pro_precioventa);
        $this->assertEquals(10, $p->pro_stockactual);
        $this->assertCount(1, $this->movs());
    }

    public function test_el_codigo_de_otro_producto_no_se_puede_usar_pero_el_propio_si(): void
    {
        $this->actingAs($this->supervisor)->post('/productos', $this->datosProducto())->assertSessionHasNoErrors();
        $otro = DB::table('productos')->where('pro_codigo', 'N-100')->value('pro_id');

        $this->put("/productos/$otro", $this->datosProducto(['pro_codigo' => 'A1']))->assertSessionHasErrors('pro_codigo');
        $this->put("/productos/$otro", $this->datosProducto(['pro_nombre' => 'Fideos finos']))->assertSessionHasNoErrors();
    }

    public function test_vender_bajo_el_costo_muestra_aviso(): void
    {
        $this->actingAs($this->supervisor)
            ->post('/productos', $this->datosProducto(['pro_preciocosto' => 8000, 'pro_precioventa' => 5000]))
            ->assertSessionHas('warning');
    }

    public function test_un_producto_con_ventas_no_se_borra_se_desactiva(): void
    {
        $this->vender(1);

        $this->actingAs($this->supervisor)->delete('/productos/1')->assertSessionHas('success');

        $this->assertSame(1, DB::table('productos')->where('pro_id', 1)->count());
        $this->assertEquals(0, DB::table('productos')->where('pro_id', 1)->value('pro_activo'));
    }

    public function test_un_producto_sin_historial_si_se_borra(): void
    {
        $this->actingAs($this->supervisor)->post('/productos', $this->datosProducto(['pro_stockactual' => 0]));
        $id = DB::table('productos')->where('pro_codigo', 'N-100')->value('pro_id');

        $this->delete("/productos/$id")->assertSessionHas('success');

        $this->assertSame(0, DB::table('productos')->where('pro_id', $id)->count());
        $this->assertSame(0, DB::table('stock_movimientos')->where('pro_id', $id)->count());
    }

    // ------------------------------------------------------------------ reimpresión

    public function test_reimprimir_ticket_marca_copia_y_queda_en_auditoria(): void
    {
        $vta = $this->vender(1);

        $this->actingAs($this->supervisor)->get("/operaciones/ventas/$vta/ticket")
            ->assertOk()->assertSee('COPIA / REIMPRESION');

        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'TICKET_REIMPRESO')->where('aud_registro_id', (string) $vta)->count());
    }

    public function test_el_ticket_original_no_dice_copia(): void
    {
        $vta = $this->vender(1);

        $this->actingAs($this->cajero)->get("/pdv/ticket-simple/$vta")->assertOk()->assertDontSee('COPIA / REIMPRESION');
    }

    public function test_una_venta_anulada_no_se_reimprime(): void
    {
        $vta = $this->vender(1);
        $this->actingAs($this->supervisor)->post("/operaciones/ventas/$vta/anular", ['motivo' => 'Error']);

        $this->get("/operaciones/ventas/$vta/ticket")->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, DB::table('auditoria')->where('aud_accion', 'TICKET_REIMPRESO')->count());
    }

    public function test_sin_permiso_de_historial_no_se_reimprime(): void
    {
        $vta = $this->vender(1);

        $this->actingAs($this->cajero)->get("/operaciones/ventas/$vta/ticket")->assertForbidden();
    }

    // ------------------------------------------------------------------ reporte ABC

    public function test_un_producto_que_pasa_el_80_por_ciento_igual_es_clase_a(): void
    {
        DB::table('productos')->insert([
            ['pro_id' => 2, 'pro_codigo' => 'B2', 'pro_nombre' => 'Aceite', 'pro_precioventa' => 1, 'pro_stockactual' => 0],
            ['pro_id' => 3, 'pro_codigo' => 'C3', 'pro_nombre' => 'Sal', 'pro_precioventa' => 1, 'pro_stockactual' => 0],
        ]);
        $this->ventaSql(1, 1, 90000, 50000);  // 90%
        $this->ventaSql(2, 1, 8000, 5000);    // 8%
        $this->ventaSql(3, 1, 2000, 1000);    // 2%

        $datos = $this->actingAs($this->supervisor)->get('/operaciones/reporte-abc')->assertOk()->viewData('datos');

        $this->assertSame('A', $datos->firstWhere('pro_id', 1)->clasificacion_abc);
        $this->assertSame('B', $datos->firstWhere('pro_id', 2)->clasificacion_abc);
        $this->assertSame('C', $datos->firstWhere('pro_id', 3)->clasificacion_abc);
    }

    public function test_un_unico_producto_vendido_es_clase_a(): void
    {
        $this->ventaSql(1, 3, 10000, 6000);

        $datos = $this->actingAs($this->supervisor)->get('/operaciones/reporte-abc')->viewData('datos');

        $this->assertCount(1, $datos);
        $this->assertSame('A', $datos[0]->clasificacion_abc);
    }

    public function test_producto_sin_costo_no_muestra_margen_del_cien_por_ciento(): void
    {
        $this->ventaSql(1, 1, 10000, 0);

        $r = $this->actingAs($this->supervisor)->get('/operaciones/reporte-abc')->assertOk();

        $this->assertTrue($r->viewData('datos')[0]->sin_costo);
        $r->assertSee('Sin costo');
        $r->assertDontSee('100.0%');
    }

    public function test_el_reporte_no_cuenta_ventas_anuladas(): void
    {
        $vta = $this->vender(2);
        $this->actingAs($this->supervisor)->post("/operaciones/ventas/$vta/anular", ['motivo' => 'Error']);

        $this->assertCount(0, $this->get('/operaciones/reporte-abc')->viewData('datos'));
    }

    public function test_exportar_el_reporte_a_excel_descarga_un_csv_con_los_datos(): void
    {
        $this->ventaSql(1, 3, 10000, 6000);

        $r = $this->actingAs($this->supervisor)->get('/operaciones/reporte-abc/excel');
        $r->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $r->headers->get('Content-Disposition'));

        $csv = $r->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Arroz', $csv);
        $this->assertStringContainsString('30000', $csv);
        $this->assertStringContainsString('40,0', $csv);  // margen 40%
    }

    public function test_exportar_pdf_muestra_la_vista_imprimible_y_se_exige_permiso(): void
    {
        $this->ventaSql(1, 1, 10000, 6000);

        $this->actingAs($this->supervisor)->get('/operaciones/reporte-abc/pdf')->assertOk()->assertSee('Rentabilidad y curva ABC')->assertSee('Arroz');
        $this->actingAs($this->cajero)->get('/operaciones/reporte-abc/excel')->assertForbidden();
    }

    public function test_el_reporte_rechaza_filtros_invalidos(): void
    {
        $this->actingAs($this->supervisor)->get('/operaciones/reporte-abc?fecha_inicio=no-es-fecha')->assertSessionHasErrors('fecha_inicio');
    }

    // ------------------------------------------------------------------ panel

    public function test_el_cajero_ve_su_dia_y_no_la_facturacion_de_la_empresa(): void
    {
        $this->ventaSql(1, 5, 10000, 6000);
        $this->vender(1);

        $r = $this->actingAs($this->cajero)->get('/dashboard')->assertOk();
        $r->assertSee('Mi día')->assertSee('Mis ventas');
        $r->assertDontSee('Este mes');
        $r->assertDontSee('Deuda de clientes');
        $this->assertFalse($r->viewData('verTodo'));
        $this->assertSame(1, $r->viewData('miDia')['cantidad']);
    }

    public function test_quien_ve_reportes_ve_todo_con_variacion_real(): void
    {
        $this->ventaSql(1, 5, 10000, 6000);

        $r = $this->actingAs($this->supervisor)->get('/dashboard')->assertOk();
        $r->assertSee('Este mes');
        $r->assertSee('Deuda de clientes');
        $r->assertDontSee('12.5%');
        $this->assertNull($r->viewData('variacionMes'));   // no hubo ventas el mes anterior
        $this->assertEquals(50000, $r->viewData('totalIngresos'));
        $this->assertSame(1, $r->viewData('hoy')['cajas_abiertas']);
    }

    public function test_la_variacion_contra_el_mes_anterior_se_calcula(): void
    {
        $this->ventaSql(1, 5, 10000, 6000);   // este mes: 50.000
        $vta = DB::table('ventas')->insertGetId([
            'suc_id' => 1, 'cli_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_id' => 1, 'vta_fecha' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
            'vta_tipo' => 'CONTADO', 'vta_total' => 25000, 'vta_estado' => 'CONFIRMADA',
        ], 'vta_id');

        $this->assertNotNull($vta);
        $r = $this->actingAs($this->supervisor)->get('/dashboard');
        $this->assertEquals(100.0, $r->viewData('variacionMes'));
    }
}
