<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 2: caja (efectivo/arqueo), anulaciones, devoluciones, cobranzas e índices.
 * SQLite en memoria; el esquema incluye la migración real de la Parte 2.
 * Ejecutar:  php artisan test --filter=CajaVentasParte2Test
 */
class CajaVentasParte2Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $cajero;
    private User $otro;
    private User $admin;
    private int $sesId;

    private const PERMISOS_CAJERO = [
        'PDV_USAR', 'CAJA_ABRIR_CERRAR', 'CAJA_INGRESO_EGRESO', 'CAJA_TRANSFERIR',
        'COBRANZAS_REGISTRAR', 'VENTAS_ANULAR', 'VENTAS_DEVOLVER',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablas();

        $this->cajero = $this->crearUsuario('caja1', self::PERMISOS_CAJERO, 'Cajero');
        $this->otro = $this->crearUsuario('caja2', self::PERMISOS_CAJERO, 'Cajero 2');
        $this->admin = $this->crearUsuario('admin', [], 'Administrador');

        DB::table('sucursales')->insert([
            'suc_id' => 1, 'suc_nombre' => 'Central', 'suc_timbrado' => '12345678',
            'suc_timbrado_inicio' => '2020-01-01', 'suc_timbrado_fin' => '2099-12-31', 'suc_factura_secuencia' => 1,
        ]);
        DB::table('cajas')->insert([
            ['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE'],
            ['caj_id' => 2, 'suc_id' => 1, 'caj_nombre' => 'Caja 2', 'caj_tipo_impresion' => 'TICKET_SIMPLE'],
            ['caj_id' => 3, 'suc_id' => 1, 'caj_nombre' => 'Caja 3', 'caj_tipo_impresion' => 'TICKET_SIMPLE'],
        ]);
        $this->sesId = DB::table('caja_sesiones')->insertGetId([
            'caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA', 'ses_monto_inicial_gs' => 100000,
        ], 'ses_id');

        DB::table('clientes')->insert([
            ['cli_id' => 1, 'cli_nombre' => 'Consumidor', 'cli_ruc_ci' => '1', 'cli_permitir_credito' => 0, 'cli_limite_credito' => 0],
            ['cli_id' => 4, 'cli_nombre' => 'Con credito', 'cli_ruc_ci' => '4', 'cli_permitir_credito' => 1, 'cli_limite_credito' => 500000],
        ]);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
            ['pro_id' => 2, 'cat_id' => 2, 'pro_nombre' => 'Leche', 'pro_precioventa' => 5000, 'pro_stockactual' => 5, 'pro_activo' => 1, 'pro_tipo_iva' => 5],
        ]);
        DB::table('cotizaciones')->insert(['cot_dolar' => 7500, 'cot_real' => 1500, 'cot_activa' => 1]);

        $this->ajustarSecuencias();
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

    private function vender(array $carrito, array $extra = [], ?User $usuario = null, int $cajId = 1): int
    {
        $r = $this->actingAs($usuario ?? $this->cajero)->postJson('/pdv/store', array_merge([
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO',
            'moneda' => 'GS', 'caj_id' => $cajId, 'carrito' => $carrito,
        ], $extra));
        $r->assertOk();

        return (int) $r->json('venta_id');
    }

    private function anular(int $vtaId, string $motivo = 'Error de carga', ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->cajero)->post("/operaciones/ventas/$vtaId/anular", ['motivo' => $motivo]);
    }

    private function devolver(int $vtaId, array $items, ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->cajero)->post("/operaciones/ventas/$vtaId/devolver", ['items' => $items, 'motivo' => 'Prueba']);
    }

    private function saldo(string $moneda = 'gs', int $cajId = 1): float
    {
        return (float) DB::table('cajas')->where('caj_id', $cajId)->value('caj_saldo_'.$moneda);
    }

    private function stock(int $proId): float
    {
        return (float) DB::table('productos')->where('pro_id', $proId)->value('pro_stockactual');
    }

    private function netoLibro(int $vtaId): float
    {
        $ing = (float) DB::table('caja_movimientos')->where('vta_id', $vtaId)->where('mov_tipo', 'INGRESO')->sum('mov_monto');
        $egr = (float) DB::table('caja_movimientos')->where('vta_id', $vtaId)->where('mov_tipo', 'EGRESO')->sum('mov_monto');

        return round($ing - $egr, 2);
    }

    private function detId(int $vtaId, int $proId): int
    {
        return (int) DB::table('detalle_ventas')->where('vta_id', $vtaId)->where('pro_id', $proId)->value('det_vta_id');
    }

    // ------------------------------------------------------------------ caja: solo el efectivo mueve el saldo

    public function test_el_efectivo_suma_al_saldo_fisico_y_la_tarjeta_no(): void
    {
        $v1 = $this->vender([['pro_id' => 1, 'cantidad' => 1]]);
        $this->assertEquals(10000, $this->saldo());

        $v2 = $this->vender([['pro_id' => 1, 'cantidad' => 1]], ['forma_pago' => 'TARJETA_DEBITO', 'nro_transferencia' => 'T-1']);
        $this->assertEquals(10000, $this->saldo(), 'La tarjeta no debe sumar al efectivo del cajón');

        $mov = DB::table('caja_movimientos')->where('vta_id', $v2)->first();
        $this->assertSame('TARJETA_DEBITO', $mov->mov_forma_pago);
        $this->assertSame('INGRESO', $mov->mov_tipo);
        $this->assertNotNull(DB::table('caja_movimientos')->where('vta_id', $v1)->value('mov_id'));
    }

    // ------------------------------------------------------------------ cierre con arqueo

    public function test_el_cierre_calcula_lo_esperado_y_la_diferencia(): void
    {
        $this->vender([['pro_id' => 1, 'cantidad' => 2]]);                                            // +20000 efectivo
        $this->vender([['pro_id' => 1, 'cantidad' => 1]], ['forma_pago' => 'QR', 'nro_transferencia' => 'Q1']); // no es efectivo
        $this->actingAs($this->cajero)->post('/finanzas/ingresos-egresos/store', [
            'tipo' => 'EGRESO', 'ses_id' => $this->sesId, 'monto' => 5000, 'concepto' => 'Compra de bolsas',
        ])->assertSessionHas('success');

        // Esperado: 100000 inicial + 20000 - 5000 = 115000. Se cuentan 114000.
        $r = $this->actingAs($this->cajero)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 114000]);
        $r->assertSessionHas('warning');

        $ses = DB::table('caja_sesiones')->where('ses_id', $this->sesId)->first();
        $this->assertSame('CERRADA', $ses->ses_estado);
        $this->assertEquals(115000, $ses->ses_esperado_gs);
        $this->assertEquals(-1000, $ses->ses_diferencia_gs);
        $this->assertEquals(0, $this->saldo());
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'CAJA_CIERRE')->count());
    }

    public function test_un_cierre_exacto_no_genera_aviso_de_diferencia(): void
    {
        $this->vender([['pro_id' => 1, 'cantidad' => 1]]);

        $r = $this->actingAs($this->cajero)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 110000]);
        $r->assertSessionHas('success');
        $this->assertEquals(0, DB::table('caja_sesiones')->where('ses_id', $this->sesId)->value('ses_diferencia_gs'));
    }

    public function test_no_se_puede_cerrar_la_caja_de_otro_ni_cerrarla_dos_veces(): void
    {
        $this->actingAs($this->otro)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 100000])
            ->assertSessionHas('error');
        $this->assertSame('ABIERTA', DB::table('caja_sesiones')->where('ses_id', $this->sesId)->value('ses_estado'));

        // Un administrador sí puede cerrarla (responsable)
        $this->actingAs($this->admin)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 100000]);
        $this->assertSame('CERRADA', DB::table('caja_sesiones')->where('ses_id', $this->sesId)->value('ses_estado'));

        $this->actingAs($this->cajero)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 1])
            ->assertSessionHas('error');
        $this->assertEquals(100000, DB::table('caja_sesiones')->where('ses_id', $this->sesId)->value('ses_monto_cierre_gs'));
    }

    // ------------------------------------------------------------------ apertura e índices únicos

    public function test_apertura_no_permite_dos_sesiones_en_la_misma_caja_ni_dos_del_mismo_usuario(): void
    {
        $this->actingAs($this->otro)->post('/finanzas/apertura', ['caj_id' => 1, 'ses_monto_inicial_gs' => 0])
            ->assertSessionHasErrors('caj_id');

        $this->actingAs($this->cajero)->post('/finanzas/apertura', ['caj_id' => 2, 'ses_monto_inicial_gs' => 0])
            ->assertSessionHasErrors('caj_id');

        $this->actingAs($this->otro)->post('/finanzas/apertura', ['caj_id' => 2, 'ses_monto_inicial_gs' => 50000])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('caja_sesiones')->where('caj_id', 2)->where('ses_estado', 'ABIERTA')->count());
    }

    public function test_el_indice_unico_impide_dos_sesiones_abiertas_en_la_misma_caja(): void
    {
        $this->expectException(QueryException::class);
        DB::table('caja_sesiones')->insert(['caj_id' => 1, 'usu_id' => $this->otro->usu_id, 'ses_estado' => 'ABIERTA']);
    }

    public function test_el_indice_unico_impide_numeros_de_factura_repetidos(): void
    {
        $fila = ['suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'vta_tipo' => 'CONTADO', 'vta_total' => 1, 'vta_estado' => 'CONFIRMADA'];

        // Sin número de factura se pueden repetir
        DB::table('ventas')->insert($fila);
        DB::table('ventas')->insert($fila);

        DB::table('ventas')->insert($fila + ['vta_timbrado' => '123', 'vta_nro_factura' => '0000001']);
        $this->expectException(QueryException::class);
        DB::table('ventas')->insert($fila + ['vta_timbrado' => '123', 'vta_nro_factura' => '0000001']);
    }

    public function test_la_migracion_se_puede_correr_dos_veces(): void
    {
        $migracion = require database_path('migrations/2026_10_09_000001_parte2_caja_ventas_auditoria.php');
        $migracion->up();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('caja_movimientos', 'mov_forma_pago'));
    }

    public function test_la_migracion_avisa_con_claridad_si_ya_hay_datos_repetidos(): void
    {
        $migracion = require database_path('migrations/2026_10_09_000001_parte2_caja_ventas_auditoria.php');

        // Se simula una base vieja: sin los índices y con datos repetidos
        DB::statement('DROP INDEX IF EXISTS uq_ventas_factura');
        DB::statement('DROP INDEX IF EXISTS uq_caja_sesion_abierta');
        $fila = ['suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'vta_tipo' => 'CONTADO', 'vta_total' => 1, 'vta_estado' => 'CONFIRMADA'];
        DB::table('ventas')->insert($fila + ['vta_timbrado' => '9', 'vta_nro_factura' => '0000005']);
        DB::table('ventas')->insert($fila + ['vta_timbrado' => '9', 'vta_nro_factura' => '0000005']);

        try {
            $migracion->up();
            $this->fail('Debió avisar de la factura repetida');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('0000005', $e->getMessage());
        }

        DB::table('ventas')->where('vta_nro_factura', '0000005')->delete();
        DB::table('caja_sesiones')->insert(['caj_id' => 1, 'usu_id' => $this->otro->usu_id, 'ses_estado' => 'ABIERTA']);

        try {
            $migracion->up();
            $this->fail('Debió avisar de la sesión abierta repetida');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('más de una sesión ABIERTA', $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ transferencias e ingresos/egresos

    public function test_transferencia_mueve_el_efectivo_entre_cajas_abiertas(): void
    {
        $this->vender([['pro_id' => 1, 'cantidad' => 3]]); // 30000 en caja 1
        DB::table('caja_sesiones')->insert(['caj_id' => 2, 'usu_id' => $this->otro->usu_id, 'ses_estado' => 'ABIERTA']);

        $this->actingAs($this->cajero)->post('/finanzas/transferir', [
            'ses_id_origen' => $this->sesId, 'caj_id_destino' => 2, 'monto' => 10000, 'moneda' => 'GS',
        ])->assertSessionHas('success');

        $this->assertEquals(20000, $this->saldo('gs', 1));
        $this->assertEquals(10000, $this->saldo('gs', 2));
        $this->assertSame(1, DB::table('caja_movimientos')->where('mov_tipo', 'EGRESO')->where('caj_id_destino', 2)->count());
    }

    public function test_transferencia_rechazada_por_saldo_destino_cerrado_misma_caja_o_sesion_ajena(): void
    {
        $this->vender([['pro_id' => 1, 'cantidad' => 1]]); // 10000
        DB::table('caja_sesiones')->insert(['caj_id' => 2, 'usu_id' => $this->otro->usu_id, 'ses_estado' => 'ABIERTA']);
        $sesOtro = (int) DB::table('caja_sesiones')->where('caj_id', 2)->value('ses_id');

        // saldo insuficiente
        $this->actingAs($this->cajero)->post('/finanzas/transferir', [
            'ses_id_origen' => $this->sesId, 'caj_id_destino' => 2, 'monto' => 999999, 'moneda' => 'GS',
        ])->assertSessionHasErrors('monto');

        // destino sin sesión abierta
        $this->actingAs($this->cajero)->post('/finanzas/transferir', [
            'ses_id_origen' => $this->sesId, 'caj_id_destino' => 3, 'monto' => 1000, 'moneda' => 'GS',
        ])->assertSessionHasErrors('monto');

        // misma caja
        $this->actingAs($this->cajero)->post('/finanzas/transferir', [
            'ses_id_origen' => $this->sesId, 'caj_id_destino' => 1, 'monto' => 1000, 'moneda' => 'GS',
        ])->assertSessionHasErrors('monto');

        // usar la sesión de otra persona como origen
        $this->actingAs($this->cajero)->post('/finanzas/transferir', [
            'ses_id_origen' => $sesOtro, 'caj_id_destino' => 1, 'monto' => 1000, 'moneda' => 'GS',
        ])->assertSessionHasErrors('monto');

        $this->assertEquals(10000, $this->saldo('gs', 1));
        $this->assertEquals(0, $this->saldo('gs', 2));
    }

    public function test_un_egreso_extra_no_puede_dejar_la_caja_en_negativo_ni_usar_una_caja_cerrada(): void
    {
        $this->vender([['pro_id' => 1, 'cantidad' => 1]]); // 10000

        $this->actingAs($this->cajero)->post('/finanzas/ingresos-egresos/store', [
            'tipo' => 'EGRESO', 'ses_id' => $this->sesId, 'monto' => 50000, 'concepto' => 'Pago',
        ])->assertSessionHas('error');
        $this->assertEquals(10000, $this->saldo());
        $this->assertSame(0, DB::table('ingresos_egresos')->count());

        $this->actingAs($this->cajero)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 110000]);
        $this->actingAs($this->cajero)->post('/finanzas/ingresos-egresos/store', [
            'tipo' => 'INGRESO', 'ses_id' => $this->sesId, 'monto' => 1000, 'concepto' => 'Fondo',
        ])->assertSessionHas('error');
        $this->assertSame(0, DB::table('ingresos_egresos')->count());
    }

    // ------------------------------------------------------------------ anulación

    public function test_anular_venta_de_contado_devuelve_stock_y_deja_la_caja_en_cero(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 2]]);
        $this->assertEquals(8, $this->stock(1));

        $this->anular($v, 'Cliente se arrepintió')->assertSessionHas('success');

        $venta = DB::table('ventas')->where('vta_id', $v)->first();
        $this->assertSame('ANULADA', $venta->vta_estado);
        $this->assertSame('Cliente se arrepintió', $venta->vta_motivo_anulacion);
        $this->assertEquals($this->cajero->usu_id, $venta->vta_anulada_por);
        $this->assertNotNull($venta->vta_anulada_fecha);
        $this->assertEquals(10, $this->stock(1));
        $this->assertEquals(0, $this->saldo());
        $this->assertEquals(0, $this->netoLibro($v));
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'VENTA_ANULADA')->count());

        // Segunda anulación: rechazada, sin movimientos nuevos
        $this->anular($v)->assertSessionHas('error');
        $this->assertEquals(10, $this->stock(1));
        $this->assertSame(2, DB::table('caja_movimientos')->where('vta_id', $v)->count());
    }

    public function test_anular_venta_a_credito_cancela_la_deuda_y_no_toca_la_caja(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 2]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']);
        $this->assertEquals(0, DB::table('caja_movimientos')->where('vta_id', $v)->count());

        $this->anular($v)->assertSessionHas('success');

        $cuenta = DB::table('cuentas_cobrar')->where('vta_id', $v)->first();
        $this->assertSame('ANULADA', $cuenta->cred_estado);
        $this->assertEquals(0, $cuenta->cred_saldo_pendiente);
        $this->assertEquals(10, $this->stock(1));
        $this->assertEquals(0, $this->saldo());
        $this->assertSame(0, DB::table('caja_movimientos')->where('vta_id', $v)->count(), 'Una venta a crédito nunca movió caja: anularla tampoco');
    }

    public function test_no_se_anula_una_venta_a_credito_que_ya_tiene_cobros(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 2]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']);
        $credId = (int) DB::table('cuentas_cobrar')->where('vta_id', $v)->value('cred_id');

        $this->actingAs($this->cajero)->postJson('/cobranzas/store', ['cred_id' => $credId, 'monto_pagar' => 5000, 'caj_id' => 1])->assertOk();

        $this->anular($v)->assertSessionHas('error');

        $this->assertSame('CONFIRMADA', DB::table('ventas')->where('vta_id', $v)->value('vta_estado'));
        $this->assertEquals(8, $this->stock(1));
        $this->assertEquals(15000, DB::table('cuentas_cobrar')->where('cred_id', $credId)->value('cred_saldo_pendiente'));
    }

    public function test_anular_una_venta_en_dolares_revierte_dolares_y_no_guaranies(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3]], ['moneda' => 'USD']); // 30000 Gs = 4.00 USD
        $this->assertEquals(4.0, $this->saldo('usd'));

        $this->anular($v)->assertSessionHas('success');

        $this->assertEquals(0, $this->saldo('usd'));
        $this->assertEquals(0, $this->saldo('gs'));
        $egreso = DB::table('caja_movimientos')->where('vta_id', $v)->where('mov_tipo', 'EGRESO')->first();
        $this->assertSame('USD', $egreso->mov_moneda);
        $this->assertEquals(4.0, $egreso->mov_monto);
    }

    public function test_anular_una_venta_con_tarjeta_no_toca_el_efectivo(): void
    {
        $this->vender([['pro_id' => 2, 'cantidad' => 1]]); // 5000 efectivo en el cajón
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 1]], ['forma_pago' => 'TARJETA_CREDITO', 'nro_transferencia' => 'X9']);

        $this->anular($v)->assertSessionHas('success');

        $this->assertEquals(5000, $this->saldo());
        $this->assertEquals(0, $this->netoLibro($v));
        $this->assertSame('TARJETA_CREDITO', DB::table('caja_movimientos')->where('vta_id', $v)->where('mov_tipo', 'EGRESO')->value('mov_forma_pago'));
    }

    public function test_anular_con_la_caja_de_origen_cerrada_exige_caja_propia_abierta(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 2]]);
        $this->actingAs($this->cajero)->post("/finanzas/cerrar/{$this->sesId}", ['cierre_gs' => 120000]);

        // Sin caja abierta no se puede devolver dinero
        $this->anular($v)->assertSessionHas('error');
        $this->assertSame('CONFIRMADA', DB::table('ventas')->where('vta_id', $v)->value('vta_estado'));
        $this->assertEquals(8, $this->stock(1));

        // Abre una caja nueva: la salida de dinero queda anotada ahí
        $nueva = DB::table('caja_sesiones')->insertGetId(['caj_id' => 2, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA'], 'ses_id');
        DB::table('cajas')->where('caj_id', 2)->update(['caj_saldo_gs' => 50000]);

        $this->anular($v)->assertSessionHas('success');
        $this->assertSame($nueva, (int) DB::table('caja_movimientos')->where('vta_id', $v)->where('mov_tipo', 'EGRESO')->value('ses_id'));
        $this->assertEquals(30000, $this->saldo('gs', 2));
    }

    public function test_sin_permiso_no_se_puede_anular_ni_devolver(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 1]]);
        $sinPermiso = $this->crearUsuario('x', ['PDV_USAR'], 'Solo POS');

        $this->actingAs($sinPermiso)->post("/operaciones/ventas/$v/anular")->assertForbidden();
        $this->actingAs($sinPermiso)->post("/operaciones/ventas/$v/devolver", ['items' => [1 => 1]])->assertForbidden();
        $this->assertSame('CONFIRMADA', DB::table('ventas')->where('vta_id', $v)->value('vta_estado'));
    }

    // ------------------------------------------------------------------ devolución

    public function test_devolucion_parcial_ajusta_stock_total_iva_caja_e_historial(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3], ['pro_id' => 2, 'cantidad' => 1]]); // 30000 + 5000
        $det = $this->detId($v, 1);

        $this->devolver($v, [$det => 1])->assertSessionHas('success');

        $venta = DB::table('ventas')->where('vta_id', $v)->first();
        $this->assertEquals(25000, $venta->vta_total);
        $this->assertEquals(round(20000 / 11, 2), (float) $venta->vta_total_iva10);
        $this->assertEquals(round(5000 / 21, 2), (float) $venta->vta_total_iva5);
        $this->assertEquals(8, $this->stock(1));                      // 10 - 3 + 1
        $this->assertEquals(2, DB::table('detalle_ventas')->where('det_vta_id', $det)->value('det_cantidad'));
        $this->assertEquals(20000, DB::table('detalle_ventas')->where('det_vta_id', $det)->value('det_subtotal'));
        $this->assertEquals(25000, $this->saldo());                   // 35000 - 10000
        $this->assertEquals(10000, DB::table('devoluciones')->where('vta_id', $v)->value('dev_total'));
        $this->assertSame(1, DB::table('detalle_devoluciones')->count());
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'VENTA_DEVOLUCION')->count());
    }

    public function test_no_se_pueden_devolver_items_de_otra_venta(): void
    {
        $a = $this->vender([['pro_id' => 1, 'cantidad' => 2]]);
        $b = $this->vender([['pro_id' => 2, 'cantidad' => 2]]);
        $detB = $this->detId($b, 2);
        $stockAntes = $this->stock(2);
        $saldoAntes = $this->saldo();

        $this->devolver($a, [$detB => 1])->assertSessionHas('error');

        $this->assertEquals(2, DB::table('detalle_ventas')->where('det_vta_id', $detB)->value('det_cantidad'));
        $this->assertEquals($stockAntes, $this->stock(2));
        $this->assertEquals($saldoAntes, $this->saldo());
        $this->assertSame(0, DB::table('devoluciones')->count());
    }

    public function test_devolucion_rechaza_cantidades_excesivas_y_acepta_fracciones(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3]]);
        $det = $this->detId($v, 1);

        $this->devolver($v, [$det => 5])->assertSessionHas('error');
        $this->assertEquals(3, DB::table('detalle_ventas')->where('det_vta_id', $det)->value('det_cantidad'));

        $this->devolver($v, [$det => 0.5])->assertSessionHas('success');
        $this->assertEquals(2.5, DB::table('detalle_ventas')->where('det_vta_id', $det)->value('det_cantidad'));
        $this->assertEquals(7.5, $this->stock(1));
        $this->assertEquals(25000, $this->saldo());

        $this->devolver($v, [$det => 0])->assertSessionHas('error'); // nada seleccionado
    }

    public function test_devolucion_en_venta_a_credito_reduce_la_deuda(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']); // 30000
        $det = $this->detId($v, 1);

        $this->devolver($v, [$det => 1])->assertSessionHas('success');

        $cuenta = DB::table('cuentas_cobrar')->where('vta_id', $v)->first();
        $this->assertEquals(20000, $cuenta->cred_saldo_pendiente);
        $this->assertEquals(20000, $cuenta->cred_monto_total);
        $this->assertSame('PENDIENTE', $cuenta->cred_estado);
        $this->assertEquals(0, $this->saldo(), 'No hay dinero para devolver: solo baja la deuda');
    }

    public function test_devolucion_a_credito_con_pagos_previos_devuelve_en_efectivo_lo_que_excede_la_deuda(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']); // 30000
        $credId = (int) DB::table('cuentas_cobrar')->where('vta_id', $v)->value('cred_id');
        $this->actingAs($this->cajero)->postJson('/cobranzas/store', ['cred_id' => $credId, 'monto_pagar' => 25000, 'caj_id' => 1])->assertOk();
        $this->assertEquals(25000, $this->saldo());

        // Devuelve 2 (20000): la deuda (5000) baja a 0 y los otros 15000 salen en efectivo
        $this->devolver($v, [$this->detId($v, 1) => 2])->assertSessionHas('success');

        $cuenta = DB::table('cuentas_cobrar')->where('cred_id', $credId)->first();
        $this->assertEquals(0, $cuenta->cred_saldo_pendiente);
        $this->assertEquals(25000, $cuenta->cred_monto_total);
        $this->assertSame('PAGADA', $cuenta->cred_estado);
        $this->assertEquals(10000, $this->saldo());
    }

    public function test_anular_despues_de_una_devolucion_parcial_deja_la_caja_en_cero(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3]]);
        $this->devolver($v, [$this->detId($v, 1) => 1])->assertSessionHas('success');

        $this->anular($v)->assertSessionHas('success');

        $this->assertEquals(10, $this->stock(1));
        $this->assertEquals(0, $this->saldo());
        $this->assertEquals(0, $this->netoLibro($v));
    }

    public function test_no_se_devuelve_una_venta_anulada(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 2]]);
        $this->anular($v);

        $this->devolver($v, [$this->detId($v, 1) => 1])->assertSessionHas('error');
        $this->assertEquals(10, $this->stock(1));
    }

    // ------------------------------------------------------------------ cobranzas

    public function test_cobranza_en_efectivo_suma_a_la_caja_y_la_tarjeta_no(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 3]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']); // 30000
        $credId = (int) DB::table('cuentas_cobrar')->where('vta_id', $v)->value('cred_id');
        $cobrar = fn (array $d) => $this->actingAs($this->cajero)->postJson('/cobranzas/store', $d + ['cred_id' => $credId, 'caj_id' => 1]);

        $cobrar(['monto_pagar' => 10000])->assertOk()->assertJson(['success' => true]);
        $this->assertEquals(10000, $this->saldo());

        $cobrar(['monto_pagar' => 5000, 'forma_pago' => 'TARJETA_DEBITO'])->assertOk();
        $this->assertEquals(10000, $this->saldo(), 'El cobro con tarjeta no suma al efectivo');
        $this->assertSame('TARJETA_DEBITO', DB::table('cobranzas')->orderByDesc('cob_id')->value('cob_formapago'));

        $cobrar(['monto_pagar' => 99999])->assertStatus(422);              // supera la deuda (15000)
        $cobrar(['monto_pagar' => 15000])->assertOk();

        $cuenta = DB::table('cuentas_cobrar')->where('cred_id', $credId)->first();
        $this->assertEquals(0, $cuenta->cred_saldo_pendiente);
        $this->assertSame('PAGADA', $cuenta->cred_estado);

        $cobrar(['monto_pagar' => 1])->assertStatus(422);                  // ya no debe nada
        $this->assertSame(3, DB::table('cobranzas')->count());
    }

    public function test_cobranza_sin_turno_abierto_es_rechazada_con_mensaje_claro(): void
    {
        $v = $this->vender([['pro_id' => 1, 'cantidad' => 1]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']);
        $credId = (int) DB::table('cuentas_cobrar')->where('vta_id', $v)->value('cred_id');

        $r = $this->actingAs($this->otro)->postJson('/cobranzas/store', ['cred_id' => $credId, 'monto_pagar' => 1000]);

        $r->assertStatus(422)->assertJson(['success' => false]);
        $this->assertEquals(10000, DB::table('cuentas_cobrar')->where('cred_id', $credId)->value('cred_saldo_pendiente'));
        $this->assertSame(0, DB::table('cobranzas')->count());
    }

    // ------------------------------------------------------------------ usuarios

    public function test_reactivar_usuario_requiere_permiso_y_queda_registrado(): void
    {
        $baja = $this->crearUsuario('baja', ['PDV_USAR'], 'Cajero 3');
        $baja->update(['usu_activo' => false]);

        $this->actingAs($this->cajero)->post("/usuarios/{$baja->usu_id}/reactivar")->assertForbidden();
        $this->assertEquals(0, (int) DB::table('usuarios')->where('usu_id', $baja->usu_id)->value('usu_activo'));

        $this->actingAs($this->admin)->post("/usuarios/{$baja->usu_id}/reactivar")->assertRedirect();
        $this->assertEquals(1, (int) DB::table('usuarios')->where('usu_id', $baja->usu_id)->value('usu_activo'));
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'USUARIO_REACTIVADO')->count());
    }

    public function test_cambiar_el_estado_de_una_promocion_ya_no_funciona_por_enlace_get(): void
    {
        $this->actingAs($this->admin)->get('/promociones/toggle/1')->assertStatus(405);
    }
}
