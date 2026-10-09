<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pruebas del cobro del PDV. Usan SQLite en memoria con tablas mínimas.
 * Ejecutar:  php artisan test --filter=PdvSeguroTest
 */
class PdvSeguroTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $cajero;
    private int $sesId;
    private int $cajId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablas();

        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero'], 'rol_id');
        $permId = DB::table('permisos')->insertGetId(['perm_codigo' => 'PDV_USAR'], 'perm_id');
        DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);

        $this->cajero = User::create([
            'rol_id' => $rolId, 'usu_usuario' => 'caja1', 'usu_email' => 'c@x.com',
            'usu_password' => Hash::make('x'), 'usu_activo' => true,
        ]);

        DB::table('sucursales')->insert([
            'suc_id' => 1, 'suc_nombre' => 'Central', 'suc_timbrado' => '12345678',
            'suc_timbrado_inicio' => '2020-01-01', 'suc_timbrado_fin' => '2099-12-31',
            'suc_factura_secuencia' => 1,
        ]);
        DB::table('cajas')->insert([
            'caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE',
        ]);
        $this->sesId = DB::table('caja_sesiones')->insertGetId([
            'caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA',
        ], 'ses_id');

        DB::table('clientes')->insert([
            ['cli_id' => 1, 'cli_nombre' => 'Consumidor', 'cli_ruc_ci' => '1', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0],
            ['cli_id' => 2, 'cli_nombre' => 'Mayorista', 'cli_ruc_ci' => '2', 'cli_es_mayorista' => 1, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0],
            ['cli_id' => 3, 'cli_nombre' => 'Bloqueado', 'cli_ruc_ci' => '3', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 1, 'cli_bloqueado' => 1, 'cli_limite_credito' => 0],
            ['cli_id' => 4, 'cli_nombre' => 'Con credito', 'cli_ruc_ci' => '4', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 1, 'cli_bloqueado' => 0, 'cli_limite_credito' => 50000],
        ]);

        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciomayorista' => 8000, 'pro_preciocosto' => 6000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
            ['pro_id' => 2, 'cat_id' => 2, 'pro_nombre' => 'Leche', 'pro_precioventa' => 5000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 3000, 'pro_stockactual' => 5, 'pro_activo' => 1, 'pro_tipo_iva' => 5],
            ['pro_id' => 3, 'cat_id' => 3, 'pro_nombre' => 'Inactivo', 'pro_precioventa' => 1000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 500, 'pro_stockactual' => 5, 'pro_activo' => 0, 'pro_tipo_iva' => 0],
        ]);

        $this->ajustarSecuencias();
    }

    private function cobrar(array $carrito, array $extra = [])
    {
        return $this->actingAs($this->cajero)->postJson('/pdv/store', array_merge([
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO',
            'moneda' => 'GS', 'caj_id' => $this->cajId, 'carrito' => $carrito,
        ], $extra));
    }

    private function stock(int $proId): float
    {
        return (float) DB::table('productos')->where('pro_id', $proId)->value('pro_stockactual');
    }

    // ------------------------------------------------------------- precios y total

    public function test_ignora_el_precio_y_el_subtotal_que_manda_el_navegador(): void
    {
        $r = $this->cobrar([['pro_id' => 1, 'cantidad' => 2, 'precio' => 1, 'subtotal' => 2]]);

        $r->assertOk()->assertJson(['success' => true, 'total_gs' => 20000]);
        $this->assertEquals(20000, DB::table('ventas')->value('vta_total'));
        $this->assertEquals(10000, DB::table('detalle_ventas')->value('det_preciounitario'));
        $this->assertEquals(6000, DB::table('detalle_ventas')->value('det_preciocosto'));
    }

    public function test_descuenta_stock_y_suma_a_caja(): void
    {
        $this->cobrar([['pro_id' => 1, 'cantidad' => 3]])->assertOk();

        $this->assertEquals(7, $this->stock(1));
        $this->assertEquals(30000, DB::table('cajas')->where('caj_id', 1)->value('caj_saldo_gs'));
        $this->assertEquals(1, DB::table('caja_movimientos')->count());
    }

    public function test_cliente_mayorista_paga_precio_mayorista(): void
    {
        $this->cobrar([['pro_id' => 1, 'cantidad' => 1]], ['cli_id' => 2])->assertOk();

        $this->assertEquals(8000, DB::table('ventas')->value('vta_total'));
    }

    public function test_promocion_porcentaje_se_aplica_en_el_servidor(): void
    {
        DB::table('promociones')->insert([
            'prom_nombre' => '10%', 'prom_tipo_descuento' => 'PORCENTAJE', 'prom_valor' => 10,
            'prom_fecha_inicio' => '2020-01-01', 'prom_fecha_fin' => '2099-12-31',
            'prom_aplica_a' => 'PRODUCTO', 'pro_id' => 1, 'prom_activa' => 1,
        ]);

        $this->cobrar([['pro_id' => 1, 'cantidad' => 1]])->assertOk();

        $this->assertEquals(9000, DB::table('ventas')->value('vta_total'));
    }

    public function test_promocion_que_deja_precio_negativo_se_ignora(): void
    {
        DB::table('promociones')->insert([
            'prom_nombre' => 'Mal cargada', 'prom_tipo_descuento' => 'MONTO', 'prom_valor' => 999999,
            'prom_fecha_inicio' => '2020-01-01', 'prom_fecha_fin' => '2099-12-31',
            'prom_aplica_a' => 'PRODUCTO', 'pro_id' => 1, 'prom_activa' => 1,
        ]);

        $this->cobrar([['pro_id' => 1, 'cantidad' => 1]])->assertOk();

        $this->assertEquals(10000, DB::table('ventas')->value('vta_total'));
    }

    public function test_calcula_el_iva_incluido(): void
    {
        $this->cobrar([['pro_id' => 1, 'cantidad' => 1], ['pro_id' => 2, 'cantidad' => 1]])->assertOk();

        $v = DB::table('ventas')->first();
        $this->assertEquals(round(10000 / 11, 2), (float) $v->vta_total_iva10);
        $this->assertEquals(round(5000 / 21, 2), (float) $v->vta_total_iva5);
    }

    // ------------------------------------------------------------------ stock

    public function test_rechaza_si_no_hay_stock(): void
    {
        $r = $this->cobrar([['pro_id' => 2, 'cantidad' => 6]]);

        $r->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('Stock insuficiente', $r->json('message'));
        $this->assertEquals(5, $this->stock(2));
        $this->assertEquals(0, DB::table('ventas')->count());
    }

    public function test_el_mismo_producto_en_dos_renglones_se_suma_y_controla_stock(): void
    {
        $r = $this->cobrar([['pro_id' => 2, 'cantidad' => 3], ['pro_id' => 2, 'cantidad' => 3]]);

        $r->assertStatus(422);
        $this->assertEquals(5, $this->stock(2));
        $this->assertEquals(0, DB::table('ventas')->count());
    }

    public function test_rechaza_cantidades_cero_o_negativas(): void
    {
        $this->cobrar([['pro_id' => 1, 'cantidad' => 0]])->assertStatus(422);
        $this->cobrar([['pro_id' => 1, 'cantidad' => -5]])->assertStatus(422);

        $this->assertEquals(10, $this->stock(1));
        $this->assertEquals(0, DB::table('ventas')->count());
    }

    public function test_rechaza_producto_inactivo_o_inexistente(): void
    {
        $this->cobrar([['pro_id' => 3, 'cantidad' => 1]])->assertStatus(422);
        $this->cobrar([['pro_id' => 999, 'cantidad' => 1]])->assertStatus(422);

        $this->assertEquals(0, DB::table('ventas')->count());
    }

    // ---------------------------------------------------------------- clientes

    public function test_cliente_bloqueado_no_puede_comprar(): void
    {
        $r = $this->cobrar([['pro_id' => 1, 'cantidad' => 1]], ['cli_id' => 3]);

        $r->assertStatus(422);
        $this->assertStringContainsString('bloqueado', $r->json('message'));
        $this->assertEquals(10, $this->stock(1));
    }

    public function test_credito_sin_habilitar_se_rechaza(): void
    {
        $this->cobrar([['pro_id' => 1, 'cantidad' => 1]], ['vta_tipo' => 'CREDITO'])->assertStatus(422);
        $this->assertEquals(0, DB::table('cuentas_cobrar')->count());
    }

    public function test_credito_sobre_el_limite_se_rechaza_con_el_total_del_servidor(): void
    {
        // 6 x 10000 = 60000 > limite 50000. El navegador dice que el subtotal es 1.
        $r = $this->cobrar([['pro_id' => 1, 'cantidad' => 6, 'subtotal' => 1]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']);

        $r->assertStatus(422);
        $this->assertStringContainsString('LÍMITE EXCEDIDO', $r->json('message'));
    }

    public function test_credito_dentro_del_limite_crea_cuenta_y_no_mueve_caja(): void
    {
        $this->cobrar([['pro_id' => 1, 'cantidad' => 2]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO'])->assertOk();

        $this->assertEquals(1, DB::table('cuentas_cobrar')->count());
        $this->assertEquals(20000, DB::table('cuentas_cobrar')->value('cred_saldo_pendiente'));
        $this->assertEquals(0, DB::table('cajas')->value('caj_saldo_gs'));
        $this->assertEquals(0, DB::table('caja_movimientos')->count());
    }

    // ----------------------------------------------------------------- factura

    public function test_las_facturas_salen_con_numero_consecutivo_y_timbrado(): void
    {
        DB::table('cajas')->update(['caj_tipo_impresion' => 'TICKET_FACTURA']);

        $this->cobrar([['pro_id' => 1, 'cantidad' => 1]])->assertOk();
        $this->cobrar([['pro_id' => 1, 'cantidad' => 1]])->assertOk();

        $this->assertEquals(['0000001', '0000002'], DB::table('ventas')->orderBy('vta_id')->pluck('vta_nro_factura')->all());
        $this->assertEquals('12345678', DB::table('ventas')->value('vta_timbrado'));
        $this->assertEquals(3, DB::table('sucursales')->value('suc_factura_secuencia'));
    }

    public function test_timbrado_vencido_no_permite_facturar_ni_gasta_numero(): void
    {
        DB::table('cajas')->update(['caj_tipo_impresion' => 'TICKET_FACTURA']);
        DB::table('sucursales')->update(['suc_timbrado_fin' => '2020-12-31']);

        $r = $this->cobrar([['pro_id' => 1, 'cantidad' => 1]]);

        $r->assertStatus(422);
        $this->assertStringContainsString('VENCIDO', $r->json('message'));
        $this->assertEquals(1, DB::table('sucursales')->value('suc_factura_secuencia'));
        $this->assertEquals(10, $this->stock(1));
    }

    // ------------------------------------------------------- errores y permisos

    public function test_un_error_tecnico_no_se_le_muestra_al_cajero(): void
    {
        Schema::drop('cuentas_cobrar'); // fuerza un fallo de base de datos en una venta a crédito

        $r = $this->cobrar([['pro_id' => 1, 'cantidad' => 1]], ['cli_id' => 4, 'vta_tipo' => 'CREDITO']);

        $r->assertStatus(500);
        $this->assertStringNotContainsString('SQLSTATE', $r->json('message'));
        $this->assertStringNotContainsString('cuentas_cobrar', $r->json('message'));
        $this->assertStringContainsString('código', $r->json('message'));
        // y la venta no quedó a medias
        $this->assertEquals(0, DB::table('ventas')->count());
        $this->assertEquals(10, $this->stock(1));
    }

    public function test_sin_permiso_pdv_usar_recibe_403(): void
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Contador'], 'rol_id');
        $otro = User::create(['rol_id' => $rolId, 'usu_usuario' => 'cont', 'usu_email' => 'k@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        $this->actingAs($otro)->postJson('/pdv/store', ['carrito' => []])->assertForbidden();
        $this->actingAs($otro)->get('/pdv')->assertForbidden();
    }

    public function test_alta_rapida_de_cliente_no_permite_credito_ni_mayorista_sin_permiso(): void
    {
        $this->actingAs($this->cajero)->postJson('/pdv/cliente-ajax', [
            'cli_ruc_ci' => '777', 'cli_nombre' => 'Nuevo', 'cli_apellido' => 'X', 'cli_telefono' => '1',
            'cli_email' => 'n@x.com', 'cli_direccion' => 'Calle',
            'cli_es_mayorista' => 1, 'cli_limite_credito' => 99999999,
            'cli_permitir_credito' => 1, 'cli_bloqueado' => 0,
        ])->assertOk();

        $c = DB::table('clientes')->where('cli_ruc_ci', '777')->first();
        $this->assertEquals(0, $c->cli_es_mayorista);
        $this->assertEquals(0, $c->cli_limite_credito);
        $this->assertEquals(0, $c->cli_permitir_credito);
    }

    // ------------------------------------------------------------------ tablas
}
