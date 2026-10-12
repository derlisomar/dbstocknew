<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Balanza;
use App\Support\Unidades;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Unidades (unidad/kg/litro), códigos de barra y etiquetas de balanza.
 * Ejecutar:  php artisan test --filter=Parte14UnidadesTest
 */
class Parte14UnidadesTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();
        config(['balanza.activa' => true, 'balanza.prefijo' => '2', 'balanza.digitos_plu' => 6, 'balanza.valor' => 'PESO']);

        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero'], 'rol_id');
        $permId = DB::table('permisos')->insertGetId(['perm_codigo' => 'PDV_USAR'], 'perm_id');
        DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        $this->cajero = User::create(['rol_id' => $rolId, 'usu_nombre' => 'Ana', 'usu_usuario' => 'caja1', 'usu_email' => 'c@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        DB::table('sucursales')->insert(['suc_id' => 1, 'suc_nombre' => 'Central', 'suc_factura_secuencia' => 1]);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE']);
        DB::table('caja_sesiones')->insert(['caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA']);
        DB::table('clientes')->insert(['cli_id' => 1, 'cli_nombre' => 'Juan', 'cli_ruc_ci' => '1-8', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0]);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_codigo' => 'A01', 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 6000, 'pro_stockactual' => 50, 'pro_activo' => 1, 'pro_tipo_iva' => 10, 'pro_unidad' => 'UN', 'pro_plu' => null],
            ['pro_id' => 2, 'cat_id' => 1, 'pro_codigo' => 'Q01', 'pro_nombre' => 'Queso', 'pro_precioventa' => 40000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 25000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10, 'pro_unidad' => 'KG', 'pro_plu' => '123'],
        ]);
        DB::table('producto_codigos')->insert(['pro_id' => 1, 'cod_codigo' => '7790001112223']);
        $this->ajustarSecuencias();
    }

    private function etiqueta(string $plu6, int $valor5): string
    {
        $c = '2'.$plu6.str_pad((string) $valor5, 5, '0', STR_PAD_LEFT);
        $s = 0;
        for ($i = 0; $i < 12; $i++) {
            $s += (int) $c[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $c.((10 - $s % 10) % 10);
    }

    public function test_helper_unidades(): void
    {
        $this->assertSame('3', Unidades::texto(3, 'UN'));
        $this->assertSame('0,25 kg', Unidades::texto(0.25, 'KG'));
        $this->assertSame('1,5 L', Unidades::texto(1.5, 'LT'));
        $this->assertTrue(Unidades::cantidadValida('KG', 0.25));
        $this->assertFalse(Unidades::cantidadValida('KG', 0.2501));
        $this->assertFalse(Unidades::cantidadValida('UN', 1.5));
        $this->assertEquals(10000, Unidades::importe(40000, 0.25, 'KG'));
        $this->assertEquals(13332, Unidades::importe(40000, 0.3333, 'KG'));
    }

    public function test_etiqueta_de_balanza(): void
    {
        $e = $this->etiqueta('000123', 250);
        $this->assertTrue(Balanza::ean13Valido($e));
        $this->assertFalse(Balanza::ean13Valido(substr($e, 0, 12).'0' === $e ? '2000123002508' : substr($e, 0, 12).(((int) $e[12] + 1) % 10)));
        $r = Balanza::resolver($e);
        $this->assertSame('balanza', $r['tipo']);
        $this->assertSame(2, $r['producto']->pro_id);
        $this->assertEquals(0.25, $r['cantidad']);
    }

    public function test_etiqueta_modo_precio_y_plu_desconocido(): void
    {
        config(['balanza.valor' => 'PRECIO']);
        $r = Balanza::resolver($this->etiqueta('000123', 10000));
        $this->assertEquals(0.25, $r['cantidad']);
        $this->assertNull(Balanza::resolver($this->etiqueta('000999', 250)));
    }

    public function test_codigo_de_barra_y_codigo_interno(): void
    {
        $this->assertSame(1, Balanza::resolver('7790001112223')['producto']->pro_id);
        $this->assertSame('barra', Balanza::resolver('A01')['tipo']);
        $this->assertNull(Balanza::resolver('NOEXISTE'));
    }

    public function test_endpoint_pdv_codigo(): void
    {
        $this->actingAs($this->cajero)->getJson('/pdv/codigo?codigo=7790001112223')->assertOk()->assertJson(['ok' => true, 'tipo' => 'barra', 'pro_id' => 1]);
        $this->actingAs($this->cajero)->getJson('/pdv/codigo?codigo='.$this->etiqueta('000123', 500))->assertOk()->assertJson(['ok' => true, 'tipo' => 'balanza', 'pro_id' => 2, 'cantidad' => 0.5]);
        $this->actingAs($this->cajero)->getJson('/pdv/codigo?codigo=999')->assertStatus(404)->assertJson(['ok' => false]);
    }

    private function vender(array $carrito)
    {
        return $this->actingAs($this->cajero)->postJson('/pdv/store', [
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1, 'carrito' => $carrito,
        ]);
    }

    public function test_venta_por_peso_descuenta_stock_decimal(): void
    {
        $this->vender([['pro_id' => 2, 'cantidad' => 0.25]])->assertOk()->assertJson(['success' => true]);
        $this->assertEquals(9.75, (float) DB::table('productos')->where('pro_id', 2)->value('pro_stockactual'));
        $this->assertEquals(10000, (float) DB::table('ventas')->value('vta_total'));
    }

    public function test_rechaza_cantidades_invalidas(): void
    {
        $this->vender([['pro_id' => 1, 'cantidad' => 1.5]])->assertStatus(422);
        $this->vender([['pro_id' => 2, 'cantidad' => 0.2501]])->assertStatus(422);
        $this->assertSame(0, DB::table('ventas')->count());
    }

    public function test_pantalla_pdv_carga(): void
    {
        $this->actingAs($this->cajero)->get('/pdv')->assertOk()->assertSee('7790001112223', false);
    }
}
