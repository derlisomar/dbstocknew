<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 5: compras a proveedores, costo, cuentas a pagar, pagos, anulación y devolución al proveedor.
 * Ejecutar:  php artisan test --filter=Parte5Test
 */
class Parte5Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $comprador;
    private User $solo_pagos;
    private User $nadie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        $this->comprador = $this->crearUsuario('compras', ['COMPRAS_REGISTRAR', 'COMPRAS_ANULAR', 'PAGOS_PROVEEDORES', 'CAJA_ABRIR_CERRAR'], 'Compras');
        $this->solo_pagos = $this->crearUsuario('pagos', ['PAGOS_PROVEEDORES'], 'Pagos');
        $this->nadie = $this->crearUsuario('nadie', ['PDV_USAR'], 'Cajero');

        DB::table('sucursales')->insert(['suc_id' => 1, 'suc_nombre' => 'Central']);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_saldo_gs' => 500000]);
        DB::table('caja_sesiones')->insert([
            'ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->comprador->usu_id, 'ses_estado' => 'ABIERTA', 'ses_monto_inicial_gs' => 500000,
        ]);
        DB::table('categorias')->insert(['cat_id' => 1, 'cat_nombre' => 'General']);
        DB::table('proveedores')->insert([
            ['prov_id' => 1, 'prov_razonsocial' => 'Distribuidora Sur', 'prov_ruc' => '800-1'],
            ['prov_id' => 2, 'prov_razonsocial' => 'Mayorista Norte', 'prov_ruc' => '800-2'],
        ]);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_codigo' => 'A1', 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciocosto' => 6000, 'pro_stockactual' => 10, 'pro_activo' => 1],
            ['pro_id' => 2, 'cat_id' => 1, 'pro_codigo' => 'B2', 'pro_nombre' => 'Aceite', 'pro_precioventa' => 20000, 'pro_preciocosto' => 0, 'pro_stockactual' => 0, 'pro_activo' => 1],
        ]);

        (require database_path('migrations/2026_10_11_000001_parte4_stock_movimientos.php'))->up();
        config(['compras.costo' => 'promedio']);
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

    private function datos(array $extra = []): array
    {
        return array_merge([
            'prov_id' => 1, 'tipo' => 'CREDITO', 'nro_documento' => '001-001-0000123',
            'items' => [['pro_id' => 1, 'cantidad' => 10, 'costo' => 9000]],
        ], $extra);
    }

    /** Registra una compra y devuelve su id. */
    private function comprar(array $extra = []): int
    {
        $this->actingAs($this->comprador)->post('/compras', $this->datos($extra))->assertSessionHasNoErrors()->assertSessionHas('success');

        return (int) DB::table('compras')->max('com_id');
    }

    private function stock(int $id = 1): float
    {
        return (float) DB::table('productos')->where('pro_id', $id)->value('pro_stockactual');
    }

    private function costo(int $id = 1): float
    {
        return (float) DB::table('productos')->where('pro_id', $id)->value('pro_preciocosto');
    }

    private function saldoCaja(): float
    {
        return (float) DB::table('cajas')->where('caj_id', 1)->value('caj_saldo_gs');
    }

    private function cuenta(int $comId): object
    {
        return DB::table('cuentas_pagar')->where('com_id', $comId)->first();
    }

    // ------------------------------------------------------------------ registrar compra

    public function test_una_compra_a_credito_suma_stock_deja_deuda_y_actualiza_el_costo(): void
    {
        $id = $this->comprar();

        $this->assertEquals(20, $this->stock());
        // Promedio ponderado: (10 x 6000 + 10 x 9000) / 20 = 7500
        $this->assertEquals(7500, $this->costo());

        $c = DB::table('compras')->where('com_id', $id)->first();
        $this->assertEquals(90000, $c->com_total);
        $this->assertSame('REGISTRADA', $c->com_estado);

        $cu = $this->cuenta($id);
        $this->assertEquals(90000, $cu->cpa_saldo_pendiente);
        $this->assertSame('PENDIENTE', $cu->cpa_estado);
        $this->assertNotNull($cu->cpa_fecha_vencimiento);

        $mov = DB::table('stock_movimientos')->where('smo_tipo', 'COMPRA')->first();
        $this->assertEquals(10, $mov->smo_cantidad);
        $this->assertEquals(20, $mov->smo_stock_resultante);
        $this->assertSame("compra:$id", $mov->smo_referencia);
        $this->assertCount(0, app(StockService::class)->descuadres());
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'COMPRA_REGISTRADA')->count());
    }

    public function test_producto_sin_costo_o_sin_stock_toma_el_costo_de_la_compra(): void
    {
        $this->comprar(['items' => [['pro_id' => 2, 'cantidad' => 5, 'costo' => 12000]]]);

        $this->assertEquals(5, $this->stock(2));
        $this->assertEquals(12000, $this->costo(2));
    }

    public function test_con_la_opcion_ultimo_costo_el_producto_pasa_a_costar_lo_ultimo_pagado(): void
    {
        config(['compras.costo' => 'ultimo']);
        $this->comprar();

        $this->assertEquals(9000, $this->costo());
    }

    public function test_dos_lineas_del_mismo_producto_se_juntan(): void
    {
        $id = $this->comprar(['items' => [
            ['pro_id' => 1, 'cantidad' => 5, 'costo' => 8000],
            ['pro_id' => 1, 'cantidad' => 5, 'costo' => 10000],
        ]]);

        $this->assertSame(1, DB::table('detalle_compras')->where('com_id', $id)->count());
        $this->assertEquals(10, DB::table('detalle_compras')->where('com_id', $id)->value('dco_cantidad'));
        $this->assertEquals(9000, DB::table('detalle_compras')->where('com_id', $id)->value('dco_costo'));
        $this->assertEquals(20, $this->stock());
    }

    public function test_compra_al_contado_en_efectivo_sale_de_la_caja_y_queda_pagada(): void
    {
        $id = $this->comprar(['tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'items' => [['pro_id' => 1, 'cantidad' => 10, 'costo' => 9000]]]);

        $this->assertEquals(500000 - 90000, $this->saldoCaja());
        $this->assertSame('PAGADA', $this->cuenta($id)->cpa_estado);
        $this->assertEquals(0, $this->cuenta($id)->cpa_saldo_pendiente);

        $mov = DB::table('caja_movimientos')->where('mov_tipo', 'EGRESO')->first();
        $this->assertSame('EFECTIVO', $mov->mov_forma_pago);
        $this->assertNotNull($mov->pag_id);
        $this->assertSame(1, DB::table('pagos_proveedores')->where('pag_estado', 'ACTIVO')->count());
    }

    public function test_compra_al_contado_por_transferencia_no_toca_el_efectivo(): void
    {
        $id = $this->comprar(['tipo' => 'CONTADO', 'forma_pago' => 'TRANSFERENCIA', 'referencia' => 'TR-55']);

        $this->assertEquals(500000, $this->saldoCaja());
        $this->assertSame(0, DB::table('caja_movimientos')->count());
        $this->assertSame('PAGADA', $this->cuenta($id)->cpa_estado);
        $this->assertSame('TR-55', DB::table('pagos_proveedores')->value('pag_referencia'));
    }

    public function test_contado_en_efectivo_sin_caja_abierta_no_guarda_nada(): void
    {
        DB::table('caja_sesiones')->update(['ses_estado' => 'CERRADA']);

        $this->actingAs($this->comprador)->post('/compras', $this->datos(['tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO']))->assertSessionHas('error');

        $this->assertSame(0, DB::table('compras')->count());
        $this->assertEquals(10, $this->stock());
        $this->assertEquals(6000, $this->costo());
    }

    public function test_contado_en_efectivo_sin_plata_en_la_caja_no_guarda_nada(): void
    {
        DB::table('cajas')->update(['caj_saldo_gs' => 1000]);

        $this->actingAs($this->comprador)->post('/compras', $this->datos(['tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO']))->assertSessionHas('error');

        $this->assertSame(0, DB::table('compras')->count());
        $this->assertEquals(10, $this->stock());
        $this->assertSame(0, DB::table('stock_movimientos')->where('smo_tipo', 'COMPRA')->count());
    }

    public function test_datos_invalidos_se_rechazan(): void
    {
        $this->actingAs($this->comprador);
        $antes = DB::table('compras')->count();

        $this->post('/compras', $this->datos(['items' => []]))->assertSessionHasErrors('items');
        $this->post('/compras', $this->datos(['items' => [['pro_id' => 1, 'cantidad' => 0, 'costo' => 100]]]))->assertSessionHasErrors('items.0.cantidad');
        $this->post('/compras', $this->datos(['items' => [['pro_id' => 1, 'cantidad' => 1, 'costo' => -5]]]))->assertSessionHasErrors('items.0.costo');
        $this->post('/compras', $this->datos(['items' => [['pro_id' => 99, 'cantidad' => 1, 'costo' => 5]]]))->assertSessionHasErrors('items.0.pro_id');
        $this->post('/compras', $this->datos(['prov_id' => 99]))->assertSessionHasErrors('prov_id');
        $this->post('/compras', $this->datos(['tipo' => 'REGALO']))->assertSessionHasErrors('tipo');

        $this->assertSame($antes, DB::table('compras')->count());
        $this->assertEquals(10, $this->stock());
    }

    public function test_fecha_futura_y_vencimiento_anterior_se_rechazan(): void
    {
        $this->actingAs($this->comprador);

        $this->post('/compras', $this->datos(['fecha' => now()->addDays(3)->toDateString()]))->assertSessionHas('error');
        $this->post('/compras', $this->datos(['fecha' => now()->toDateString(), 'vencimiento' => now()->subDays(2)->toDateString()]))->assertSessionHas('error');
        $this->assertSame(0, DB::table('compras')->count());
    }

    public function test_el_mismo_documento_del_mismo_proveedor_no_se_carga_dos_veces(): void
    {
        $this->comprar();

        $this->actingAs($this->comprador)->post('/compras', $this->datos())->assertSessionHas('error');
        $this->assertSame(1, DB::table('compras')->count());

        // Otro proveedor, o el mismo documento ya anulado, sí se puede.
        $this->actingAs($this->comprador)->post('/compras', $this->datos(['prov_id' => 2]))->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('compras')->count());
    }

    public function test_costo_mayor_al_precio_de_venta_muestra_aviso(): void
    {
        $this->actingAs($this->comprador)
            ->post('/compras', $this->datos(['items' => [['pro_id' => 1, 'cantidad' => 1, 'costo' => 200000]]]))
            ->assertSessionHas('warning');
    }

    // ------------------------------------------------------------------ pagos

    public function test_pagos_parciales_y_total_van_bajando_la_deuda(): void
    {
        $id = $this->comprar();
        $cpa = $this->cuenta($id)->cpa_id;

        $this->actingAs($this->solo_pagos)->post("/cuentas-pagar/$cpa/pagar", ['monto' => 30000, 'forma_pago' => 'TRANSFERENCIA', 'referencia' => 'T1'])
            ->assertSessionHas('success');
        $this->assertEquals(60000, $this->cuenta($id)->cpa_saldo_pendiente);
        $this->assertSame('PENDIENTE', $this->cuenta($id)->cpa_estado);

        $this->post("/cuentas-pagar/$cpa/pagar", ['monto' => 60000, 'forma_pago' => 'CHEQUE'])->assertSessionHas('success');
        $this->assertEquals(0, $this->cuenta($id)->cpa_saldo_pendiente);
        $this->assertSame('PAGADA', $this->cuenta($id)->cpa_estado);

        // Ya no hay deuda: otro pago se rechaza.
        $this->post("/cuentas-pagar/$cpa/pagar", ['monto' => 1, 'forma_pago' => 'OTRO'])->assertSessionHas('error');
        $this->assertSame(2, DB::table('pagos_proveedores')->count());
    }

    public function test_no_se_puede_pagar_mas_de_lo_que_se_debe(): void
    {
        $id = $this->comprar();
        $cpa = $this->cuenta($id)->cpa_id;

        $this->actingAs($this->comprador)->post("/cuentas-pagar/$cpa/pagar", ['monto' => 90001, 'forma_pago' => 'TRANSFERENCIA'])->assertSessionHas('error');
        $this->assertEquals(90000, $this->cuenta($id)->cpa_saldo_pendiente);
        $this->assertSame(0, DB::table('pagos_proveedores')->count());
    }

    public function test_pago_en_efectivo_sale_de_la_caja_y_al_anularlo_vuelve(): void
    {
        $id = $this->comprar();
        $cpa = $this->cuenta($id)->cpa_id;

        $this->actingAs($this->comprador)->post("/cuentas-pagar/$cpa/pagar", ['monto' => 40000, 'forma_pago' => 'EFECTIVO'])->assertSessionHas('success');
        $this->assertEquals(460000, $this->saldoCaja());
        $pagId = (int) DB::table('pagos_proveedores')->value('pag_id');

        $this->post("/pagos-proveedores/$pagId/anular", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post("/pagos-proveedores/$pagId/anular", ['motivo' => 'Monto mal cargado'])->assertSessionHas('success');

        $this->assertEquals(500000, $this->saldoCaja());
        $this->assertEquals(90000, $this->cuenta($id)->cpa_saldo_pendiente);
        $this->assertSame('PENDIENTE', $this->cuenta($id)->cpa_estado);
        $this->assertSame('ANULADO', DB::table('pagos_proveedores')->where('pag_id', $pagId)->value('pag_estado'));

        // No se anula dos veces.
        $this->post("/pagos-proveedores/$pagId/anular", ['motivo' => 'Otra vez'])->assertSessionHas('error');
        $this->assertEquals(500000, $this->saldoCaja());
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'PAGO_PROVEEDOR_ANULADO')->count());
    }

    public function test_pago_en_efectivo_sin_caja_abierta_se_rechaza(): void
    {
        $id = $this->comprar();
        $cpa = $this->cuenta($id)->cpa_id;

        // El usuario "solo pagos" no tiene caja abierta.
        $this->actingAs($this->solo_pagos)->post("/cuentas-pagar/$cpa/pagar", ['monto' => 1000, 'forma_pago' => 'EFECTIVO'])->assertSessionHas('error');
        $this->assertSame(0, DB::table('pagos_proveedores')->count());
        $this->assertEquals(90000, $this->cuenta($id)->cpa_saldo_pendiente);
    }

    // ------------------------------------------------------------------ anular compra

    public function test_anular_una_compra_saca_el_stock_y_revierte_los_pagos(): void
    {
        $id = $this->comprar(['tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO']);
        $this->assertEquals(410000, $this->saldoCaja());

        $this->actingAs($this->comprador)->post("/compras/$id/anular", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->post("/compras/$id/anular", ['motivo' => 'Cargada dos veces'])->assertSessionHas('success');

        $this->assertEquals(10, $this->stock());
        $this->assertEquals(500000, $this->saldoCaja());
        $this->assertSame('ANULADA', DB::table('compras')->where('com_id', $id)->value('com_estado'));
        $this->assertSame('ANULADA', $this->cuenta($id)->cpa_estado);
        $this->assertSame('ANULADO', DB::table('pagos_proveedores')->value('pag_estado'));
        $this->assertSame(1, DB::table('stock_movimientos')->where('smo_tipo', 'ANULACION_COMPRA')->count());
        $this->assertCount(0, app(StockService::class)->descuadres());

        // No se anula dos veces.
        $this->post("/compras/$id/anular", ['motivo' => 'Otra vez'])->assertSessionHas('error');
        $this->assertEquals(10, $this->stock());
    }

    public function test_no_se_puede_anular_si_parte_de_la_mercaderia_ya_se_vendio(): void
    {
        $id = $this->comprar(['items' => [['pro_id' => 2, 'cantidad' => 5, 'costo' => 12000]]]);
        // Se vendieron 4 de las 5 unidades: quedan 1 y no alcanza para sacar las 5.
        app(StockService::class)->mover(2, 'VENTA', -4, 'venta de prueba');

        $this->actingAs($this->comprador)->post("/compras/$id/anular", ['motivo' => 'Error'])->assertSessionHas('error');

        $this->assertSame('REGISTRADA', DB::table('compras')->where('com_id', $id)->value('com_estado'));
        $this->assertEquals(1, $this->stock(2));
        $this->assertSame('PENDIENTE', $this->cuenta($id)->cpa_estado);
    }

    // ------------------------------------------------------------------ devolución al proveedor

    public function test_devolver_al_proveedor_baja_el_stock_y_la_deuda(): void
    {
        $id = $this->comprar();
        $dco = (int) DB::table('detalle_compras')->where('com_id', $id)->value('dco_id');

        $this->actingAs($this->comprador)->post("/compras/$id/devolver", ['items' => [$dco => 4], 'motivo' => 'Llegó roto'])->assertSessionHas('success');

        $this->assertEquals(16, $this->stock());
        $this->assertEquals(4, DB::table('detalle_compras')->where('dco_id', $dco)->value('dco_devuelta'));
        // 4 x 9000 = 36000 menos de deuda
        $this->assertEquals(54000, $this->cuenta($id)->cpa_saldo_pendiente);
        $this->assertEquals(54000, $this->cuenta($id)->cpa_monto_total);
        $this->assertSame(1, DB::table('stock_movimientos')->where('smo_tipo', 'DEVOLUCION_PROVEEDOR')->count());
        $this->assertCount(0, app(StockService::class)->descuadres());
    }

    public function test_no_se_puede_devolver_mas_de_lo_comprado_ni_mas_del_stock(): void
    {
        $id = $this->comprar();
        $dco = (int) DB::table('detalle_compras')->where('com_id', $id)->value('dco_id');
        $this->actingAs($this->comprador);

        $this->post("/compras/$id/devolver", ['items' => [$dco => 11]])->assertSessionHas('error');
        $this->post("/compras/$id/devolver", ['items' => [$dco => 0]])->assertSessionHas('error');

        // Se vende casi todo: no se puede devolver lo que ya no está.
        app(StockService::class)->mover(1, 'VENTA', -18, 'venta de prueba');
        $this->post("/compras/$id/devolver", ['items' => [$dco => 5]])->assertSessionHas('error');

        $this->assertEquals(2, $this->stock());
        $this->assertEquals(0, DB::table('detalle_compras')->where('dco_id', $dco)->value('dco_devuelta'));
    }

    public function test_devolver_algo_ya_pagado_avisa_que_el_proveedor_debe_reembolsar(): void
    {
        $id = $this->comprar(['tipo' => 'CONTADO', 'forma_pago' => 'TRANSFERENCIA']);
        $dco = (int) DB::table('detalle_compras')->where('com_id', $id)->value('dco_id');

        $this->actingAs($this->comprador)->post("/compras/$id/devolver", ['items' => [$dco => 2]])
            ->assertSessionHas('success')->assertSessionHas('warning');

        $this->assertEquals(0, $this->cuenta($id)->cpa_saldo_pendiente);
        $this->assertSame('PAGADA', $this->cuenta($id)->cpa_estado);
    }

    public function test_al_anular_una_compra_con_devolucion_solo_sale_lo_que_quedaba(): void
    {
        $id = $this->comprar();
        $dco = (int) DB::table('detalle_compras')->where('com_id', $id)->value('dco_id');
        $this->actingAs($this->comprador)->post("/compras/$id/devolver", ['items' => [$dco => 4]]);

        $this->post("/compras/$id/anular", ['motivo' => 'Error'])->assertSessionHas('success');

        $this->assertEquals(10, $this->stock());
        $this->assertCount(0, app(StockService::class)->descuadres());
    }

    // ------------------------------------------------------------------ pantallas y permisos

    public function test_las_pantallas_cargan_con_datos(): void
    {
        $id = $this->comprar();
        $this->actingAs($this->comprador);

        $this->get('/compras')->assertOk()->assertSee('Distribuidora Sur')->assertSee('Con deuda');
        $this->get('/compras?estado=ANULADA')->assertOk()->assertSee('No hay compras');
        $this->get('/compras/nueva')->assertOk()->assertSee('Registrar compra');
        $this->get("/compras/$id")->assertOk()->assertSee('Arroz')->assertSee('Anular esta compra');
        $this->get('/cuentas-pagar')->assertOk()->assertSee('Deuda por proveedor')->assertSee('Registrar pago');
        $this->get('/cuentas-pagar?estado=VENCIDAS&prov_id=1')->assertOk();
    }

    public function test_cuentas_a_pagar_agrupa_la_antiguedad(): void
    {
        $this->comprar(['nro_documento' => 'A', 'vencimiento' => now()->addDays(10)->toDateString()]);
        $this->comprar(['nro_documento' => 'B', 'fecha' => now()->subDays(60)->toDateString(), 'vencimiento' => now()->subDays(15)->toDateString()]);
        $this->comprar(['nro_documento' => 'C', 'fecha' => now()->subDays(100)->toDateString(), 'vencimiento' => now()->subDays(90)->toDateString()]);

        $r = $this->actingAs($this->comprador)->get('/cuentas-pagar')->assertOk();
        $a = $r->viewData('antiguedad');

        $this->assertEquals(90000, $a['vigente']);
        $this->assertEquals(90000, $a['d30']);
        $this->assertEquals(0, $a['d60']);
        $this->assertEquals(90000, $a['d60mas']);
        $this->assertEquals(270000, $r->viewData('deudaTotal'));
    }

    public function test_los_permisos_se_respetan(): void
    {
        $id = $this->comprar();
        $cpa = $this->cuenta($id)->cpa_id;

        $this->actingAs($this->nadie);
        $this->get('/compras')->assertForbidden();
        $this->get('/cuentas-pagar')->assertForbidden();
        $this->post('/compras', $this->datos(['nro_documento' => 'X']))->assertForbidden();
        $this->post("/compras/$id/anular", ['motivo' => 'x'])->assertForbidden();

        // Solo pagos: ve cuentas y paga, pero no registra ni anula compras.
        $this->actingAs($this->solo_pagos);
        $this->get('/cuentas-pagar')->assertOk();
        $this->get('/compras/nueva')->assertForbidden();
        $this->post("/compras/$id/anular", ['motivo' => 'x'])->assertForbidden();
        $this->post("/cuentas-pagar/$cpa/pagar", ['monto' => 1000, 'forma_pago' => 'OTRO'])->assertSessionHas('success');

        $this->assertSame('REGISTRADA', DB::table('compras')->where('com_id', $id)->value('com_estado'));
    }

    public function test_un_proveedor_con_compras_no_se_puede_borrar(): void
    {
        $this->comprar();
        $admin = $this->crearUsuario('admin', [], 'Administrador');

        $this->actingAs($admin)->delete('/proveedores/1')->assertSessionHas('error');
        $this->assertSame(1, DB::table('proveedores')->where('prov_id', 1)->count());

        $this->delete('/proveedores/2')->assertSessionHas('success');
        $this->assertSame(0, DB::table('proveedores')->where('prov_id', 2)->count());
    }

    public function test_la_migracion_se_puede_correr_dos_veces(): void
    {
        $m = require database_path('migrations/2026_10_12_000001_parte5_compras.php');
        $m->up();
        $m->up();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('caja_movimientos', 'pag_id'));
    }

    public function test_el_verificador_revisa_la_parte_5(): void
    {
        Artisan::call('sistema:verificar');
        $this->assertStringContainsString('Parte 5', Artisan::output());
    }
}
