<?php

namespace Tests\Feature;

use App\Exceptions\NegocioException;
use App\Models\User;
use App\Services\ConfiguracionService;
use App\Services\ContabilidadInformes;
use App\Services\ContabilidadMotor;
use App\Services\ContabilidadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 10: módulo contable (asientos automáticos, ajustes, libros, balances e IVA).
 * Ejecutar:  php artisan test --filter=Parte10Test
 */
class Parte10Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private ContabilidadService $c;
    private ContabilidadMotor $motor;
    private User $conta;
    private User $lector;
    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        $this->conta = $this->usuario('conta', ['CONTABILIDAD_VER', 'CONTABILIDAD_GESTIONAR'], 'Contador');
        $this->lector = $this->usuario('lector', ['CONTABILIDAD_VER'], 'Lector');
        $this->cajero = $this->usuario('cajero', ['PDV_USAR'], 'Cajero');

        DB::table('sucursales')->insert(['suc_id' => 1, 'suc_nombre' => 'Central']);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1']);
        DB::table('caja_sesiones')->insert(['ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->conta->usu_id, 'ses_estado' => 'ABIERTA',
            'ses_fecha_apertura' => now()->subHours(3)]);
        DB::table('clientes')->insert(['cli_id' => 1, 'cli_nombre' => 'Cliente', 'cli_apellido' => 'Prueba', 'cli_ruc_ci' => '1234567-8', 'cli_permitir_credito' => 1]);
        DB::table('categorias')->insert([['cat_id' => 1, 'cat_nombre' => 'Mercaderías'], ['cat_id' => 2, 'cat_nombre' => 'Servicios']]);
        DB::table('proveedores')->insert(['prov_id' => 1, 'prov_razonsocial' => 'Distribuidora Sur', 'prov_ruc' => '800123-4']);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_nombre' => 'Arroz', 'pro_precioventa' => 11000, 'pro_preciocosto' => 6000, 'pro_stockactual' => 100, 'pro_tipo_iva' => 10],
            ['pro_id' => 2, 'cat_id' => 1, 'pro_nombre' => 'Pan', 'pro_precioventa' => 2100, 'pro_preciocosto' => 1000, 'pro_stockactual' => 100, 'pro_tipo_iva' => 5],
            ['pro_id' => 3, 'cat_id' => 2, 'pro_nombre' => 'Instalación', 'pro_precioventa' => 50000, 'pro_preciocosto' => 0, 'pro_stockactual' => 0, 'pro_tipo_iva' => 0],
        ]);
        DB::table('cotizaciones')->insert(['cot_fecha' => now()->subDays(5)->toDateString(), 'cot_dolar' => 7500, 'cot_real' => 1500, 'cot_activa' => true]);

        $this->c = app(ContabilidadService::class);
        $this->motor = app(ContabilidadMotor::class);
        $this->c->preparar(now()->subDays(2)->toDateString());
    }

    // ------------------------------------------------------------------ ayudas

    private function usuario(string $login, array $permisos, string $rol): User
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => $rol], 'rol_id');
        foreach ($permisos as $codigo) {
            $permId = DB::table('permisos')->where('perm_codigo', $codigo)->value('perm_id')
                ?? DB::table('permisos')->insertGetId(['perm_codigo' => $codigo], 'perm_id');
            DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        }

        return User::create(['rol_id' => $rolId, 'usu_usuario' => $login, 'usu_email' => "$login@x.com", 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
    }

    /** Venta con detalle: $items = [[pro_id, cantidad, subtotal(con IVA), costo]] */
    private function vender(array $items, string $tipo = 'CONTADO', string $forma = 'EFECTIVO', string $moneda = 'GS', ?string $fecha = null, ?float $total = null): int
    {
        $total ??= array_sum(array_column($items, 2));
        $id = DB::table('ventas')->insertGetId([
            'suc_id' => 1, 'cli_id' => 1, 'usu_id' => $this->conta->usu_id, 'ses_id' => 1, 'vta_fecha' => $fecha ?? now(),
            'vta_tipo' => $tipo, 'vta_formapago' => $forma, 'vta_moneda' => $moneda, 'vta_total' => $total, 'vta_estado' => 'CONFIRMADA',
            'vta_timbrado' => '12345678', 'vta_nro_factura' => '001-001-000000'.random_int(1, 9),
        ], 'vta_id');
        foreach ($items as [$pro, $cant, $sub, $costo]) {
            DB::table('detalle_ventas')->insert(['vta_id' => $id, 'pro_id' => $pro, 'det_cantidad' => $cant, 'det_preciounitario' => $sub / $cant, 'det_subtotal' => $sub, 'det_preciocosto' => $costo]);
        }

        return $id;
    }

    private function saldo(string $clave): float
    {
        $cue = DB::table('cont_cuentas')->where('cue_clave', $clave)->first();
        $s = DB::table('cont_lineas')->where('cue_id', $cue->cue_id)->selectRaw('COALESCE(SUM(lin_debe),0) d, COALESCE(SUM(lin_haber),0) h')->first();

        return round(ContabilidadService::naturalezaDeudora($cue->cue_tipo) ? $s->d - $s->h : $s->h - $s->d, 2);
    }

    private function sync(): array
    {
        return $this->motor->sincronizar();
    }

    private function totalesCuadran(): void
    {
        $t = DB::table('cont_lineas')->selectRaw('SUM(lin_debe) d, SUM(lin_haber) h')->first();
        $this->assertEqualsWithDelta((float) $t->d, (float) $t->h, 0.01, 'El debe y el haber de todo el libro deben ser iguales');
    }

    // ------------------------------------------------------------------ preparación y plan

    public function test_preparar_crea_el_plan_base_y_es_repetible(): void
    {
        $this->assertTrue($this->c->preparada());
        $n = DB::table('cont_cuentas')->count();
        $this->assertGreaterThan(25, $n);
        $this->assertSame(0, $this->c->preparar());
        $this->assertSame($n, DB::table('cont_cuentas')->count());
        $this->assertSame(0, DB::table('cont_cuentas')->whereNull('cue_clave')->where('cue_codigo', '1.1.01')->count());
    }

    public function test_sin_preparar_el_motor_no_hace_nada(): void
    {
        ConfiguracionService::set(['cont_inicio' => null]);
        $this->vender([[1, 1, 11000, 6000]]);
        $this->assertSame(0, $this->sync()['generados']);
        $this->assertSame(0, DB::table('cont_asientos')->count());
    }

    // ------------------------------------------------------------------ ventas

    public function test_venta_contado_genera_ingreso_iva_y_costo(): void
    {
        // 1 arroz (10 %) por 11.000 y 2 panes (5 %) por 4.200: total 15.200
        $this->vender([[1, 1, 11000, 6000], [2, 2, 4200, 1000]]);
        $r = $this->sync();

        $this->assertSame([], $r['errores']);
        $this->assertSame(15200.0, $this->saldo('caja_gs'));
        $this->assertSame(1000.0 + 200.0, $this->saldo('iva_debito')); // 11000/11 + 4200/21
        $this->assertSame(15200.0 - 1200.0, $this->saldo('ventas_mercaderias'));
        $this->assertSame(8000.0, $this->saldo('cmv'));               // 6000 + 2*1000
        $this->assertSame(-8000.0, $this->saldo('mercaderias'));      // sin apertura todavía
        $this->totalesCuadran();
    }

    public function test_el_motor_no_duplica_asientos(): void
    {
        $this->vender([[1, 1, 11000, 6000]]);
        $this->sync();
        $antes = DB::table('cont_asientos')->count();
        $r = $this->sync();
        $this->assertSame(0, $r['generados']);
        $this->assertSame($antes, DB::table('cont_asientos')->count());
    }

    public function test_las_ventas_anteriores_a_la_fecha_de_inicio_no_se_contabilizan(): void
    {
        $this->vender([[1, 1, 11000, 6000]], fecha: now()->subDays(10)->toDateString());
        $this->sync();
        $this->assertSame(0, DB::table('cont_asientos')->count());
    }

    public function test_el_mapeo_lleva_cada_forma_de_pago_y_categoria_a_su_cuenta(): void
    {
        $banco2 = DB::table('cont_cuentas')->insertGetId(['cue_codigo' => '1.1.04.2', 'cue_nombre' => 'Banco Familiar', 'cue_tipo' => 'ACTIVO', 'cue_imputable' => true, 'cue_activa' => true], 'cue_id');
        $serv = DB::table('cont_cuentas')->where('cue_clave', 'servicios')->value('cue_id');
        DB::table('cont_mapeos')->insert([
            ['map_tipo' => 'PAGO', 'map_clave' => 'TRANSFERENCIA', 'cue_id' => $banco2],
            ['map_tipo' => 'CAT_INGRESO', 'map_clave' => '2', 'cue_id' => $serv],
        ]);
        $this->c->olvidarCache();

        $v = $this->vender([[3, 1, 50000, 0]], 'CONTADO', 'TRANSFERENCIA');
        $this->sync();

        $asi = DB::table('cont_asientos')->where('asi_clave', 'VENTA:'.$v)->value('asi_id');
        $cuentas = DB::table('cont_lineas')->where('asi_id', $asi)->pluck('cue_id')->all();
        $this->assertContains($banco2, $cuentas);
        $this->assertContains($serv, $cuentas);
        $this->assertSame(0, DB::table('cont_asientos')->where('asi_clave', 'CMV:'.$v)->count(), 'Un servicio sin costo no genera asiento de costo');
        $this->assertSame(0, DB::table('cont_lineas')->where('asi_id', $asi)->where('cue_id', $this->c->cuentaPorClave('iva_debito'))->count(), 'Exento: sin IVA');
    }

    public function test_el_descuento_o_redondeo_no_rompe_el_asiento(): void
    {
        // El total cobrado es menor que la suma de ítems (descuento global).
        $this->vender([[1, 1, 11000, 6000]], total: 10000);
        $r = $this->sync();
        $this->assertSame([], $r['errores']);
        $this->assertSame(10000.0, $this->saldo('caja_gs'));
        $this->totalesCuadran();
    }

    public function test_venta_a_credito_y_cobranza(): void
    {
        $this->vender([[1, 1, 11000, 6000]], 'CREDITO');
        $this->sync();
        $this->assertSame(11000.0, $this->saldo('clientes'));
        $this->assertSame(0.0, $this->saldo('caja_gs'));

        DB::table('cobranzas')->insert(['suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'cob_fecha' => now(), 'cob_monto_total' => 11000, 'cob_estado' => 'ACTIVA', 'cob_formapago' => 'EFECTIVO']);
        $this->sync();

        $this->assertSame(0.0, $this->saldo('clientes'));
        $this->assertSame(11000.0, $this->saldo('caja_gs'));
        $this->totalesCuadran();
    }

    public function test_cobranza_en_dolares_genera_diferencia_de_cambio(): void
    {
        $this->vender([[1, 1, 75000, 6000]], 'CREDITO');
        $cob = DB::table('cobranzas')->insertGetId(['suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'cob_fecha' => now(), 'cob_monto_total' => 75000, 'cob_estado' => 'ACTIVA', 'cob_formapago' => 'EFECTIVO'], 'cob_id');
        DB::table('caja_movimientos')->insert(['ses_id' => 1, 'mov_tipo' => 'INGRESO', 'mov_monto' => 10, 'mov_moneda' => 'USD', 'mov_forma_pago' => 'EFECTIVO', 'cob_id' => $cob]);
        DB::table('cotizaciones')->insert(['cot_fecha' => now()->toDateString(), 'cot_dolar' => 7600, 'cot_real' => 1500, 'cot_activa' => true]);
        $this->sync();

        // 10 USD a 7.600 = 76.000 contra una deuda de 75.000: ganancia de 1.000
        $this->assertSame(76000.0, $this->saldo('caja_usd'));
        $this->assertSame(0.0, $this->saldo('clientes'));
        $this->assertSame(1000.0, $this->saldo('dif_cambio_ganancia'));
        $this->totalesCuadran();
    }

    public function test_anular_la_venta_deja_todo_en_cero(): void
    {
        $v = $this->vender([[1, 1, 11000, 6000]]);
        $this->sync();
        DB::table('ventas')->where('vta_id', $v)->update(['vta_estado' => 'ANULADA', 'vta_anulada_fecha' => now()]);
        $this->sync();

        foreach (['caja_gs', 'iva_debito', 'ventas_mercaderias', 'cmv', 'mercaderias'] as $clave) {
            $this->assertSame(0.0, $this->saldo($clave), $clave);
        }
        $this->assertSame(0, $this->sync()['generados'], 'No se vuelve a revertir');
    }

    public function test_devolucion_descuenta_ingreso_iva_y_vuelve_el_costo(): void
    {
        $v = $this->vender([[1, 2, 22000, 6000]]);
        $this->sync();
        $det = DB::table('detalle_ventas')->where('vta_id', $v)->value('det_vta_id');
        $dev = DB::table('devoluciones')->insertGetId(['vta_id' => $v, 'dev_fecha' => now(), 'dev_total' => 11000], 'dev_id');
        DB::table('detalle_devoluciones')->insert(['dev_id' => $dev, 'det_vta_id' => $det, 'pro_id' => 1, 'ddv_cantidad' => 1, 'ddv_preciounitario' => 11000, 'ddv_subtotal' => 11000]);
        $this->sync();

        $this->assertSame(11000.0, $this->saldo('caja_gs'));
        $this->assertSame(1000.0, $this->saldo('iva_debito'));
        $this->assertSame(6000.0, $this->saldo('cmv'));
        $this->totalesCuadran();

        // Si después se anula la venta entera, todo vuelve a cero sin duplicar.
        DB::table('ventas')->where('vta_id', $v)->update(['vta_estado' => 'ANULADA', 'vta_anulada_fecha' => now()]);
        $this->sync();
        foreach (['caja_gs', 'iva_debito', 'ventas_mercaderias', 'cmv', 'mercaderias'] as $clave) {
            $this->assertSame(0.0, $this->saldo($clave), $clave);
        }
    }

    // ------------------------------------------------------------------ compras y pagos

    public function test_compra_y_pago_a_proveedor(): void
    {
        $com = DB::table('compras')->insertGetId(['prov_id' => 1, 'usu_id' => 1, 'com_fecha' => now(), 'com_tipo' => 'CREDITO', 'com_total' => 110000, 'com_estado' => 'REGISTRADA', 'com_nro_documento' => '001-001-1'], 'com_id');
        DB::table('detalle_compras')->insert(['com_id' => $com, 'pro_id' => 1, 'dco_cantidad' => 10, 'dco_costo' => 11000, 'dco_subtotal' => 110000, 'dco_devuelta' => 0]);
        $this->sync();

        $this->assertSame(100000.0, $this->saldo('mercaderias'));
        $this->assertSame(10000.0, $this->saldo('iva_credito'));
        $this->assertSame(110000.0, $this->saldo('proveedores'));

        DB::table('pagos_proveedores')->insert(['cpa_id' => 1, 'prov_id' => 1, 'usu_id' => 1, 'pag_fecha' => now(), 'pag_monto' => 110000, 'pag_forma_pago' => 'TRANSFERENCIA', 'pag_estado' => 'ACTIVO']);
        $this->sync();
        $this->assertSame(0.0, $this->saldo('proveedores'));
        $this->assertSame(-110000.0, $this->saldo('banco'));
        $this->totalesCuadran();

        // Anular el pago y la compra
        DB::table('pagos_proveedores')->update(['pag_estado' => 'ANULADO', 'pag_anulado_fecha' => now()]);
        DB::table('compras')->update(['com_estado' => 'ANULADA', 'com_anulada_fecha' => now()]);
        $this->sync();
        foreach (['mercaderias', 'iva_credito', 'proveedores', 'banco'] as $clave) {
            $this->assertSame(0.0, $this->saldo($clave), $clave);
        }
    }

    public function test_devolucion_al_proveedor_baja_la_deuda_y_el_inventario(): void
    {
        $com = DB::table('compras')->insertGetId(['prov_id' => 1, 'usu_id' => 1, 'com_fecha' => now(), 'com_tipo' => 'CREDITO', 'com_total' => 110000, 'com_estado' => 'REGISTRADA'], 'com_id');
        DB::table('detalle_compras')->insert(['com_id' => $com, 'pro_id' => 1, 'dco_cantidad' => 10, 'dco_costo' => 11000, 'dco_subtotal' => 110000, 'dco_devuelta' => 0]);
        DB::table('stock_movimientos')->insert(['pro_id' => 1, 'smo_tipo' => 'DEVOLUCION_PROVEEDOR', 'smo_cantidad' => -2, 'smo_stock_resultante' => 8, 'smo_referencia' => 'compra:'.$com, 'smo_fecha' => now()->subMinutes(5)]);
        $this->sync();

        $this->assertSame(110000.0 - 22000.0, $this->saldo('proveedores'));
        $this->assertSame(100000.0 - 20000.0, $this->saldo('mercaderias'));
        $this->assertSame(10000.0 - 2000.0, $this->saldo('iva_credito'));
        $this->totalesCuadran();
    }

    // ------------------------------------------------------------------ caja y ajustes

    public function test_ingresos_y_egresos_de_caja(): void
    {
        DB::table('ingresos_egresos')->insert([
            ['ses_id' => 1, 'ie_tipo' => 'EGRESO', 'ie_monto' => 30000, 'ie_concepto' => 'Pago de luz', 'ie_fecha' => now()],
            ['ses_id' => 1, 'ie_tipo' => 'INGRESO', 'ie_monto' => 5000, 'ie_concepto' => 'Aporte', 'ie_fecha' => now()],
        ]);
        $this->sync();
        $this->assertSame(30000.0, $this->saldo('gastos_generales'));
        $this->assertSame(5000.0, $this->saldo('otros_ingresos'));
        $this->assertSame(-25000.0, $this->saldo('caja_gs'));
    }

    public function test_cierre_de_caja_con_faltante_y_diferencia_de_cambio(): void
    {
        DB::table('cotizaciones')->insert(['cot_fecha' => now()->toDateString(), 'cot_dolar' => 7600, 'cot_real' => 1500, 'cot_activa' => true]);
        // Un ingreso de 100 USD registrado ayer cuando el dólar valía 7.500.
        DB::table('caja_movimientos')->insert(['ses_id' => 1, 'mov_tipo' => 'INGRESO', 'mov_monto' => 100, 'mov_moneda' => 'USD', 'mov_forma_pago' => 'EFECTIVO', 'mov_fecha' => now()->subDays(1)]);
        DB::table('caja_sesiones')->where('ses_id', 1)->update([
            'ses_estado' => 'CERRADA', 'ses_fecha_cierre' => now(),
            'ses_esperado_gs' => 100000, 'ses_diferencia_gs' => -5000,
            'ses_esperado_usd' => 100, 'ses_diferencia_usd' => 0,
            'ses_esperado_brl' => 0, 'ses_diferencia_brl' => 0,
        ]);
        $this->sync();

        $this->assertSame(5000.0, $this->saldo('faltante_caja'));
        $this->assertSame(-5000.0, $this->saldo('caja_gs'));
        // 100 USD pasaron de valer 750.000 a 760.000: ganancia por diferencia de cambio de 10.000
        $this->assertSame(10000.0, $this->saldo('dif_cambio_ganancia'));
        $this->assertSame(10000.0, $this->saldo('caja_usd'));
        $this->assertSame(0, $this->sync()['generados']);
        $this->totalesCuadran();
    }

    public function test_ajuste_de_stock_por_faltante_se_contabiliza_al_costo(): void
    {
        DB::table('stock_movimientos')->insert([
            ['pro_id' => 1, 'smo_tipo' => 'CONTEO', 'smo_cantidad' => -3, 'smo_stock_resultante' => 97, 'smo_motivo' => 'Conteo físico', 'smo_fecha' => now()->subMinutes(5)],
            ['pro_id' => 1, 'smo_tipo' => 'VENTA', 'smo_cantidad' => -1, 'smo_stock_resultante' => 96, 'smo_motivo' => null, 'smo_fecha' => now()->subMinutes(5)],
            ['pro_id' => 2, 'smo_tipo' => 'AJUSTE_ENTRADA', 'smo_cantidad' => 4, 'smo_stock_resultante' => 104, 'smo_motivo' => null, 'smo_fecha' => now()->subMinutes(5)],
        ]);
        $this->sync();

        $this->assertSame(18000.0, $this->saldo('faltante_inventario')); // 3 × 6.000
        $this->assertSame(4000.0, $this->saldo('sobrante_inventario'));  // 4 × 1.000
        $this->assertSame(-14000.0, $this->saldo('mercaderias'));
        $this->assertSame(2, DB::table('cont_asientos')->where('asi_origen', 'AJUSTE_STOCK')->count(), 'La VENTA no es un ajuste');
    }

    // ------------------------------------------------------------------ asientos manuales y cierre

    public function test_asiento_manual_debe_cuadrar(): void
    {
        $caja = $this->c->cuentaPorClave('caja_gs');
        $falt = $this->c->cuentaPorClave('faltante_caja');

        $id = $this->c->crearAsiento(now()->toDateString(), 'Faltante', 'MANUAL', null, [[$falt, 1000, 0], [$caja, 0, 1000]], $this->conta->usu_id, true);
        $this->assertNotNull($id);
        $this->assertSame(1000.0, $this->saldo('faltante_caja'));

        $this->expectException(NegocioException::class);
        $this->c->crearAsiento(now()->toDateString(), 'Descuadrado', 'MANUAL', null, [[$falt, 1000, 0], [$caja, 0, 900]], null, true);
    }

    public function test_no_se_puede_usar_una_cuenta_titulo_ni_inactiva(): void
    {
        $titulo = DB::table('cont_cuentas')->where('cue_codigo', '1')->value('cue_id');
        $caja = $this->c->cuentaPorClave('caja_gs');
        $this->expectException(NegocioException::class);
        $this->c->crearAsiento(now()->toDateString(), 'X', 'MANUAL', null, [[$titulo, 10, 0], [$caja, 0, 10]], null, true);
    }

    public function test_periodo_cerrado_bloquea_manuales_y_corre_los_automaticos(): void
    {
        ConfiguracionService::set(['cont_cerrado_hasta' => now()->subDay()->toDateString()]);
        $caja = $this->c->cuentaPorClave('caja_gs');
        $falt = $this->c->cuentaPorClave('faltante_caja');

        try {
            $this->c->crearAsiento(now()->subDays(2)->toDateString(), 'Atrasado', 'MANUAL', null, [[$falt, 10, 0], [$caja, 0, 10]], null, true);
            $this->fail('Debió rechazar el asiento en período cerrado');
        } catch (NegocioException $e) {
            $this->assertStringContainsString('cerrado', $e->getMessage());
        }

        $this->vender([[1, 1, 11000, 6000]], fecha: now()->subDay()->toDateString());
        $this->sync();
        $fecha = DB::table('cont_asientos')->where('asi_origen', 'VENTA')->value('asi_fecha');
        $this->assertSame(now()->toDateString(), Carbon::parse($fecha)->toDateString());
    }

    public function test_anular_un_asiento_manual_lo_contrarresta(): void
    {
        $caja = $this->c->cuentaPorClave('caja_gs');
        $falt = $this->c->cuentaPorClave('faltante_caja');
        $id = $this->c->crearAsiento(now()->toDateString(), 'Faltante', 'MANUAL', null, [[$falt, 1000, 0], [$caja, 0, 1000]], 1, true);
        $this->c->anularManual($id, 'Error de carga', 1);

        $this->assertSame(0.0, $this->saldo('faltante_caja'));
        $this->assertSame('ANULADO', DB::table('cont_asientos')->where('asi_id', $id)->value('asi_estado'));
        $this->expectException(NegocioException::class);
        $this->c->anularManual($id, 'Otra vez', 1);
    }

    // ------------------------------------------------------------------ informes

    public function test_balance_cuadra_con_apertura_y_libro_iva(): void
    {
        // Apertura: caja 100.000, mercaderías 600.000, capital 700.000
        $this->c->crearAsiento(now()->subDay()->toDateString(), 'Apertura', 'MANUAL', null, [
            [$this->c->cuentaPorClave('caja_gs'), 100000, 0], [$this->c->cuentaPorClave('mercaderias'), 600000, 0], [$this->c->cuentaPorClave('capital'), 0, 700000],
        ], null, true);
        $this->vender([[1, 1, 11000, 6000], [2, 2, 4200, 1000], [3, 1, 50000, 0]]);
        $this->sync();

        $inf = app(ContabilidadInformes::class);
        $b = $inf->balance(now()->toDateString());
        $this->assertTrue($b['cuadra']);
        $this->assertSame(100000.0 + 65200.0, $b['total_activo'] - 600000.0 + 8000.0);

        $r = $inf->resultados(now()->subDay()->toDateString(), now()->toDateString());
        $this->assertSame(65200.0 - 1200.0 - 8000.0, $r['resultado']); // ventas netas menos costo

        $s = $inf->sumas(null, now()->toDateString());
        $this->assertEqualsWithDelta($s->sum('debe'), $s->sum('haber'), 0.01);

        $iva = $inf->libroIvaVentas(now()->toDateString(), now()->toDateString());
        $this->assertCount(1, $iva);
        $this->assertSame(10000.0, $iva[0]['base10']);
        $this->assertSame(1000.0, $iva[0]['iva10']);
        $this->assertSame(4000.0, $iva[0]['base5']);
        $this->assertSame(200.0, $iva[0]['iva5']);
        $this->assertSame(50000.0, $iva[0]['exento']);
        $this->assertSame('12345678', $iva[0]['timbrado']);

        $m = $inf->mayor($this->c->cuentaPorClave('caja_gs'), now()->toDateString(), now()->toDateString());
        $this->assertSame(100000.0, $m['anterior']);
        $this->assertSame(165200.0, $m['saldo']);
    }

    public function test_el_libro_iva_compras_trae_proveedor_y_timbrado(): void
    {
        $com = DB::table('compras')->insertGetId(['prov_id' => 1, 'usu_id' => 1, 'com_fecha' => now(), 'com_tipo' => 'CONTADO', 'com_total' => 11000, 'com_estado' => 'REGISTRADA', 'com_nro_documento' => '001-002-77', 'com_timbrado' => '99887766'], 'com_id');
        DB::table('detalle_compras')->insert(['com_id' => $com, 'pro_id' => 1, 'dco_cantidad' => 1, 'dco_costo' => 11000, 'dco_subtotal' => 11000, 'dco_devuelta' => 0]);
        $f = app(ContabilidadInformes::class)->libroIvaCompras(now()->toDateString(), now()->toDateString());
        $this->assertSame('800123-4', $f[0]['ruc']);
        $this->assertSame('99887766', $f[0]['timbrado']);
        $this->assertSame(1000.0, $f[0]['iva10']);
    }

    // ------------------------------------------------------------------ pantallas, permisos y edición

    public function test_las_pantallas_cargan_para_quien_tiene_permiso(): void
    {
        $this->vender([[1, 1, 11000, 6000]]);
        $this->actingAs($this->conta);
        foreach (['/contabilidad', '/contabilidad/diario', '/contabilidad/mayor', '/contabilidad/sumas-y-saldos', '/contabilidad/estado-de-resultados',
            '/contabilidad/balance-general', '/contabilidad/libro-iva/ventas', '/contabilidad/libro-iva/compras', '/contabilidad/plan-de-cuentas',
            '/contabilidad/mapeos', '/contabilidad/asiento-manual'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->assertSame(1, DB::table('cont_asientos')->where('asi_origen', 'VENTA')->count(), 'Al abrir las pantallas se pone al día solo');
    }

    public function test_el_csv_del_libro_iva_se_descarga(): void
    {
        $this->vender([[1, 1, 11000, 6000]]);
        $r = $this->actingAs($this->lector)->get('/contabilidad/libro-iva/ventas?exportar=csv&desde='.now()->toDateString().'&hasta='.now()->toDateString());
        $r->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('content-type'));
        $this->assertStringContainsString('Gravado 10%', $r->streamedContent());
    }

    public function test_quien_solo_ve_no_puede_gestionar_y_quien_no_tiene_permiso_no_entra(): void
    {
        $this->actingAs($this->lector)->get('/contabilidad/diario')->assertOk();
        $this->actingAs($this->lector)->get('/contabilidad/mapeos')->assertForbidden();
        $this->actingAs($this->lector)->post('/contabilidad/asiento-manual', [])->assertForbidden();
        $this->actingAs($this->lector)->post('/contabilidad/cierre-de-periodo', ['hasta' => now()->subDay()->toDateString()])->assertForbidden();
        $this->actingAs($this->cajero)->get('/contabilidad')->assertForbidden();
    }

    public function test_la_edicion_basica_no_incluye_contabilidad(): void
    {
        ConfiguracionService::set(['edicion' => 'BASICA']);
        $this->actingAs($this->conta)->get('/contabilidad')->assertRedirect(route('dashboard'));
        $this->assertSame(0, $this->sync()['generados']);
        $this->vender([[1, 1, 11000, 6000]]);
        $this->assertSame(0, $this->sync()['generados'], 'Con el módulo apagado el motor no trabaja');
    }

    public function test_guardar_un_asiento_manual_desde_la_pantalla(): void
    {
        $caja = $this->c->cuentaPorClave('caja_gs');
        $falt = $this->c->cuentaPorClave('faltante_caja');
        $datos = ['fecha' => now()->toDateString(), 'glosa' => 'Faltante detectado en conteo', 'lineas' => [
            ['cue_id' => $falt, 'debe' => 2500, 'haber' => '', 'detalle' => ''],
            ['cue_id' => $caja, 'debe' => '', 'haber' => 2500, 'detalle' => ''],
        ]];

        $this->actingAs($this->conta)->post('/contabilidad/asiento-manual', $datos)->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame(2500.0, $this->saldo('faltante_caja'));
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'ASIENTO_MANUAL')->count());

        $datos['lineas'][1]['haber'] = 100;
        $this->actingAs($this->conta)->post('/contabilidad/asiento-manual', $datos)->assertSessionHas('error');
        $this->assertSame(2500.0, $this->saldo('faltante_caja'));
    }

    public function test_guardar_mapeos_y_rechazar_cuentas_invalidas(): void
    {
        $banco = $this->c->cuentaPorClave('banco');
        $this->actingAs($this->conta)->post('/contabilidad/mapeos', ['pago' => ['QR' => $banco], 'cat_ingreso' => [2 => $this->c->cuentaPorClave('servicios')]])->assertSessionHas('success');
        $this->assertSame($banco, DB::table('cont_mapeos')->where('map_tipo', 'PAGO')->where('map_clave', 'QR')->value('cue_id'));

        $titulo = DB::table('cont_cuentas')->where('cue_codigo', '1')->value('cue_id');
        $this->actingAs($this->conta)->post('/contabilidad/mapeos', ['pago' => ['QR' => $titulo]])->assertSessionHas('error');
        $this->assertSame($banco, DB::table('cont_mapeos')->where('map_clave', 'QR')->value('cue_id'));
    }

    public function test_cerrar_y_reabrir_periodos(): void
    {
        $ayer = now()->subDay()->toDateString();
        $this->actingAs($this->conta)->post('/contabilidad/cierre-de-periodo', ['hasta' => $ayer])->assertSessionHas('success');
        $this->assertSame($ayer, ConfiguracionService::get('cont_cerrado_hasta'));
        $this->actingAs($this->conta)->post('/contabilidad/cierre-de-periodo', ['hasta' => $ayer])->assertSessionHas('error');
        $this->actingAs($this->conta)->post('/contabilidad/cierre-de-periodo', ['reabrir' => 1])->assertSessionHas('success');
        $this->assertNull(ConfiguracionService::get('cont_cerrado_hasta'));
    }

    public function test_crear_y_desactivar_cuentas(): void
    {
        $this->actingAs($this->conta)->post('/contabilidad/plan-de-cuentas', ['codigo' => '5.2.09', 'nombre' => 'Publicidad', 'tipo' => 'EGRESO', 'imputable' => 1])->assertSessionHas('success');
        $id = DB::table('cont_cuentas')->where('cue_codigo', '5.2.09')->value('cue_id');
        $this->actingAs($this->conta)->post('/contabilidad/plan-de-cuentas', ['codigo' => '5.2.09', 'nombre' => 'Otra', 'tipo' => 'EGRESO'])->assertSessionHas('error');
        $this->actingAs($this->conta)->put('/contabilidad/plan-de-cuentas/'.$id, ['nombre' => 'Publicidad web'])->assertSessionHas('success');
        $this->assertSame(0, (int) DB::table('cont_cuentas')->where('cue_id', $id)->value('cue_activa'));

        $caja = DB::table('cont_cuentas')->where('cue_clave', 'caja_gs')->value('cue_id');
        $this->actingAs($this->conta)->put('/contabilidad/plan-de-cuentas/'.$caja, ['nombre' => 'Caja'])->assertSessionHas('error');
    }

    public function test_el_comando_sincroniza(): void
    {
        $this->vender([[1, 1, 11000, 6000]]);
        $this->artisan('contabilidad:sincronizar')->assertSuccessful();
        $this->assertSame(1, DB::table('cont_asientos')->where('asi_origen', 'VENTA')->count());
    }

    public function test_la_pagina_publica_presenta_la_contabilidad_solo_en_la_completa(): void
    {
        $r = $this->get('/');
        $r->assertOk()->assertSee('Contabilidad integrada')->assertSee('edición completa', false);
        $r->assertDontSee('electrónica');
        $r->assertDontSee('factura electr');
    }
}
