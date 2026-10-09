<?php

namespace Tests\Feature;

use App\Exceptions\NegocioException;
use App\Models\Presupuesto;
use App\Models\User;
use App\Services\PresupuestoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 6: presupuestos con fecha de validez, sin descuento de stock hasta convertirlos en venta.
 * Ejecutar:  php artisan test --filter=Parte6Test
 */
class Parte6Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $vendedor;   // gestiona presupuestos
    private User $cajero;     // solo vende en el PDV
    private User $nadie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        $this->vendedor = $this->crearUsuario('vendedor', ['PRESUPUESTOS_GESTIONAR', 'PDV_USAR'], 'Vendedor');
        $this->cajero = $this->crearUsuario('cajero', ['PDV_USAR'], 'Cajero');
        $this->nadie = $this->crearUsuario('nadie', ['CLIENTES_GESTIONAR'], 'Otro');

        DB::table('sucursales')->insert(['suc_id' => 1, 'suc_nombre' => 'Central', 'suc_factura_secuencia' => 1]);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE']);
        DB::table('caja_sesiones')->insert(['ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA']);

        DB::table('clientes')->insert([
            ['cli_id' => 1, 'cli_nombre' => 'Consumidor', 'cli_ruc_ci' => '1', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0],
            ['cli_id' => 2, 'cli_nombre' => 'Mayorista SA', 'cli_ruc_ci' => '2', 'cli_es_mayorista' => 1, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0],
        ]);
        DB::table('categorias')->insert(['cat_id' => 1, 'cat_nombre' => 'General']);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_codigo' => 'A1', 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciomayorista' => 8000, 'pro_preciocosto' => 6000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
            ['pro_id' => 2, 'cat_id' => 1, 'pro_codigo' => 'B2', 'pro_nombre' => 'Aceite', 'pro_precioventa' => 20000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 12000, 'pro_stockactual' => 5, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
            ['pro_id' => 3, 'cat_id' => 1, 'pro_codigo' => 'C3', 'pro_nombre' => 'Inactivo', 'pro_precioventa' => 1000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 500, 'pro_stockactual' => 5, 'pro_activo' => 0, 'pro_tipo_iva' => 10],
        ]);
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

    private function datos(array $extra = []): array
    {
        return array_merge([
            'cli_id' => 1, 'validez_dias' => 7,
            'items' => [['pro_id' => 1, 'cantidad' => 2]],
        ], $extra);
    }

    private function crear(array $extra = []): Presupuesto
    {
        return app(PresupuestoService::class)->crear($this->datos($extra), $this->vendedor);
    }

    /** Presupuesto armado a mano con una fecha de vencimiento y un estado concretos. */
    private function directo(string $estado, int $diasAlVencimiento, float $total = 10000): Presupuesto
    {
        $p = Presupuesto::create([
            'cli_id' => 1, 'usu_id' => $this->vendedor->usu_id, 'pre_fecha' => now(), 'pre_validez_dias' => 7,
            'pre_fecha_vencimiento' => \Carbon\Carbon::parse(Presupuesto::hoy())->addDays($diasAlVencimiento)->toDateString(),
            'pre_total' => $total, 'pre_estado' => $estado,
        ]);
        DB::table('detalle_presupuestos')->insert(['pre_id' => $p->pre_id, 'pro_id' => 1, 'dpr_cantidad' => 1, 'dpr_precio' => $total, 'dpr_subtotal' => $total]);

        return $p;
    }

    private function stock(int $id): float
    {
        return (float) DB::table('productos')->where('pro_id', $id)->value('pro_stockactual');
    }

    private function vender(Presupuesto $p, array $carrito = null, User $como = null)
    {
        return $this->actingAs($como ?? $this->cajero)->postJson('/pdv/store', [
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1,
            'presupuesto_id' => $p->pre_id,
            'carrito' => $carrito ?? [['pro_id' => 1, 'cantidad' => 1]],
        ]);
    }

    // ------------------------------------------------------------------ crear

    public function test_crea_presupuesto_con_validez_y_precios_del_servidor(): void
    {
        $p = $this->crear(['items' => [['pro_id' => 1, 'cantidad' => 2], ['pro_id' => 2, 'cantidad' => 1]]]);

        $this->assertEquals('BORRADOR', $p->pre_estado);
        $this->assertEquals(40000, (float) $p->pre_total);
        $this->assertEquals(7, $p->pre_validez_dias);
        $this->assertEquals(\Carbon\Carbon::parse(Presupuesto::hoy())->addDays(7)->toDateString(), $p->pre_fecha_vencimiento->toDateString());
        $this->assertEquals(2, DB::table('detalle_presupuestos')->where('pre_id', $p->pre_id)->count());
        $this->assertEquals(1, DB::table('auditoria')->where('aud_accion', 'PRESUPUESTO_CREAR')->count());
    }

    public function test_cliente_mayorista_cotiza_con_precio_mayorista(): void
    {
        $p = $this->crear(['cli_id' => 2, 'items' => [['pro_id' => 1, 'cantidad' => 1]]]);

        $this->assertEquals(8000, (float) $p->pre_total);
    }

    public function test_el_navegador_no_puede_fijar_el_precio(): void
    {
        $r = $this->actingAs($this->vendedor)->post('/presupuestos', $this->datos(['items' => [['pro_id' => 1, 'cantidad' => 1, 'precio' => 1]]]));

        $r->assertRedirect();
        $this->assertEquals(10000, (float) DB::table('presupuestos')->value('pre_total'));
        $this->assertEquals(10000, (float) DB::table('detalle_presupuestos')->value('dpr_precio'));
    }

    public function test_crear_no_mueve_stock_ni_caja(): void
    {
        $this->crear();

        $this->assertEquals(10, $this->stock(1));
        $this->assertEquals(0, DB::table('stock_movimientos')->count());
        $this->assertEquals(0, DB::table('caja_movimientos')->count());
    }

    public function test_validez_fuera_de_rango_se_rechaza(): void
    {
        foreach ([0, 91, -3] as $dias) {
            $this->actingAs($this->vendedor)->post('/presupuestos', $this->datos(['validez_dias' => $dias]))->assertSessionHasErrors('validez_dias');
        }
        $this->assertEquals(0, DB::table('presupuestos')->count());
    }

    public function test_pide_cliente_o_nombre(): void
    {
        $r = $this->actingAs($this->vendedor)->post('/presupuestos', $this->datos(['cli_id' => null]));
        $r->assertSessionHas('error');
        $this->assertEquals(0, DB::table('presupuestos')->count());

        $this->actingAs($this->vendedor)->post('/presupuestos', $this->datos(['cli_id' => null, 'cliente_nombre' => 'Juan Pérez']))->assertRedirect();
        $this->assertEquals('Juan Pérez', DB::table('presupuestos')->value('pre_cliente_nombre'));
    }

    public function test_productos_repetidos_se_suman_y_los_inactivos_se_rechazan(): void
    {
        $p = $this->crear(['items' => [['pro_id' => 1, 'cantidad' => 1], ['pro_id' => 1, 'cantidad' => 2]]]);
        $this->assertEquals(1, DB::table('detalle_presupuestos')->where('pre_id', $p->pre_id)->count());
        $this->assertEquals(30000, (float) $p->pre_total);

        $this->expectException(NegocioException::class);
        $this->crear(['items' => [['pro_id' => 3, 'cantidad' => 1]]]);
    }

    public function test_cotizar_un_producto_sin_stock_es_posible(): void
    {
        $p = $this->crear(['items' => [['pro_id' => 1, 'cantidad' => 500]]]);

        $this->assertEquals(5000000, (float) $p->pre_total);
        $this->assertEquals(10, $this->stock(1));
    }

    // ------------------------------------------------------------------ vigentes, vencidos, por vender

    public function test_listados_vigentes_vencidos_y_por_vender(): void
    {
        $this->directo('BORRADOR', 5);
        $this->directo('ENVIADO', 0);        // vence hoy: sigue vigente
        $this->directo('ACEPTADO', 3);       // por vender
        $this->directo('ACEPTADO', -1);      // aceptado pero vencido
        $this->directo('ENVIADO', -10);      // vencido
        $this->directo('RECHAZADO', 5);
        $this->directo('FACTURADO', 5);

        $this->assertEquals(3, Presupuesto::vigentes()->count());
        $this->assertEquals(2, Presupuesto::vencidos()->count());
        $this->assertEquals(1, Presupuesto::porVender()->count());
    }

    public function test_el_vencimiento_se_calcula_sin_proceso_automatico(): void
    {
        $p = $this->directo('ENVIADO', 0);
        $this->assertFalse($p->estaVencido());
        $this->assertEquals('ENVIADO', $p->estadoVisible());

        $p->update(['pre_fecha_vencimiento' => \Carbon\Carbon::parse(Presupuesto::hoy())->subDay()->toDateString()]);
        $p = $p->fresh();
        $this->assertTrue($p->estaVencido());
        $this->assertEquals('VENCIDO', $p->estadoVisible());
        $this->assertEquals('ENVIADO', $p->pre_estado); // lo guardado no cambia
    }

    public function test_pantalla_de_listado_muestra_las_tres_listas(): void
    {
        $this->directo('BORRADOR', 5);
        $this->directo('ACEPTADO', 3);
        $this->directo('ENVIADO', -2);

        foreach (['vigentes' => 2, 'por_vender' => 1, 'vencidos' => 1, 'todos' => 3] as $vista => $cantidad) {
            $r = $this->actingAs($this->vendedor)->get('/presupuestos?vista='.$vista);
            $r->assertOk();
            $this->assertCount($cantidad, $r->viewData('presupuestos'), $vista);
        }

        $r = $this->actingAs($this->vendedor)->get('/presupuestos');
        $this->assertEquals(2, $r->viewData('resumen')['vigentes']['cantidad']);
        $this->assertEquals(1, $r->viewData('resumen')['vencidos']['cantidad']);
        $this->assertEquals(1, $r->viewData('resumen')['por_vender']['cantidad']);
    }

    public function test_busqueda_por_cliente_y_numero(): void
    {
        $a = $this->crear(['cli_id' => 2]);
        $this->crear(['cli_id' => 1]);

        $r = $this->actingAs($this->vendedor)->get('/presupuestos?vista=todos&q=Mayorista');
        $this->assertCount(1, $r->viewData('presupuestos'));

        $r = $this->actingAs($this->vendedor)->get('/presupuestos?vista=todos&q='.$a->numero);
        $this->assertCount(1, $r->viewData('presupuestos'));
    }

    // ------------------------------------------------------------------ estados

    public function test_aceptar_pasa_a_por_vender_sin_tocar_stock(): void
    {
        $p = $this->crear();
        $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/estado", ['estado' => 'ACEPTADO'])->assertSessionHas('success');

        $this->assertEquals('ACEPTADO', $p->fresh()->pre_estado);
        $this->assertEquals(1, Presupuesto::porVender()->count());
        $this->assertEquals(10, $this->stock(1));
        $this->assertEquals(0, DB::table('stock_movimientos')->count());
    }

    public function test_no_se_acepta_un_presupuesto_vencido(): void
    {
        $p = $this->directo('ENVIADO', -1);

        $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/estado", ['estado' => 'ACEPTADO'])->assertSessionHas('error');
        $this->assertEquals('ENVIADO', $p->fresh()->pre_estado);
    }

    public function test_rechazar_y_reabrir(): void
    {
        $p = $this->crear();
        $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/estado", ['estado' => 'RECHAZADO', 'motivo' => 'Muy caro']);
        $this->assertEquals('RECHAZADO', $p->fresh()->pre_estado);
        $this->assertEquals('Muy caro', $p->fresh()->pre_rechazo_motivo);

        $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/estado", ['estado' => 'BORRADOR']);
        $this->assertEquals('BORRADOR', $p->fresh()->pre_estado);
    }

    public function test_renovar_vuelve_vigente_un_vencido(): void
    {
        $p = $this->directo('ACEPTADO', -4);
        $this->assertEquals(1, Presupuesto::vencidos()->count());

        $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/renovar", ['validez_dias' => 15])->assertSessionHas('success');

        $this->assertEquals(0, Presupuesto::vencidos()->count());
        $this->assertEquals(1, Presupuesto::porVender()->count());
        $this->assertEquals(\Carbon\Carbon::parse(Presupuesto::hoy())->addDays(15)->toDateString(), $p->fresh()->pre_fecha_vencimiento->toDateString());
    }

    public function test_no_se_renueva_uno_rechazado_o_vendido(): void
    {
        foreach (['RECHAZADO', 'FACTURADO'] as $estado) {
            $p = $this->directo($estado, -3);
            $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/renovar", ['validez_dias' => 7])->assertSessionHas('error');
        }
    }

    // ------------------------------------------------------------------ editar

    public function test_edita_borrador_y_recalcula_total(): void
    {
        $p = $this->crear();
        $this->actingAs($this->vendedor)->put("/presupuestos/{$p->pre_id}", $this->datos(['validez_dias' => 15, 'items' => [['pro_id' => 2, 'cantidad' => 3]]]))->assertRedirect();

        $p = $p->fresh();
        $this->assertEquals(60000, (float) $p->pre_total);
        $this->assertEquals(15, $p->pre_validez_dias);
        $this->assertEquals(1, DB::table('detalle_presupuestos')->where('pre_id', $p->pre_id)->count());
    }

    public function test_no_se_edita_un_presupuesto_aceptado(): void
    {
        $p = $this->directo('ACEPTADO', 5);

        $this->actingAs($this->vendedor)->put("/presupuestos/{$p->pre_id}", $this->datos())->assertSessionHas('error');
        $this->actingAs($this->vendedor)->get("/presupuestos/{$p->pre_id}/editar")->assertRedirect();
    }

    // ------------------------------------------------------------------ permisos

    public function test_permisos(): void
    {
        $p = $this->directo('BORRADOR', 5);

        // El cajero ve el listado y el detalle, pero no crea ni cambia estados.
        $this->actingAs($this->cajero)->get('/presupuestos')->assertOk();
        $this->actingAs($this->cajero)->get("/presupuestos/{$p->pre_id}")->assertOk();
        $this->actingAs($this->cajero)->get("/presupuestos/{$p->pre_id}/imprimir")->assertOk();
        $this->actingAs($this->cajero)->get('/presupuestos/nuevo')->assertForbidden();
        $this->actingAs($this->cajero)->post('/presupuestos', $this->datos())->assertForbidden();
        $this->actingAs($this->cajero)->post("/presupuestos/{$p->pre_id}/estado", ['estado' => 'ACEPTADO'])->assertForbidden();
        $this->actingAs($this->cajero)->post("/presupuestos/{$p->pre_id}/renovar", ['validez_dias' => 7])->assertForbidden();

        // Sin ningún permiso relacionado no se ve nada.
        $this->actingAs($this->nadie)->get('/presupuestos')->assertForbidden();
        $this->actingAs($this->nadie)->get("/presupuestos/{$p->pre_id}")->assertForbidden();
    }

    public function test_imprimir_muestra_numero_validez_y_total(): void
    {
        $p = $this->crear();

        $r = $this->actingAs($this->vendedor)->get("/presupuestos/{$p->pre_id}/imprimir");
        $r->assertOk()->assertSee($p->numero)->assertSee($p->pre_fecha_vencimiento->format('d/m/Y'))->assertSee('20.000');
    }

    // ------------------------------------------------------------------ convertir en venta

    public function test_convertir_en_venta_descuenta_stock_y_deja_el_presupuesto_vendido(): void
    {
        $p = $this->crear(['items' => [['pro_id' => 1, 'cantidad' => 2]]]);
        $this->actingAs($this->vendedor)->post("/presupuestos/{$p->pre_id}/estado", ['estado' => 'ACEPTADO']);
        $this->assertEquals(10, $this->stock(1));

        $this->vender($p, [['pro_id' => 1, 'cantidad' => 2]])->assertOk()->assertJson(['success' => true]);

        $p = $p->fresh();
        $this->assertEquals('FACTURADO', $p->pre_estado);
        $this->assertNotNull($p->vta_id);
        $this->assertEquals($p->vta_id, DB::table('ventas')->value('vta_id'));
        $this->assertEquals(8, $this->stock(1));
        $this->assertEquals(0, Presupuesto::porVender()->count());
        $this->assertEquals(1, DB::table('auditoria')->where('aud_accion', 'PRESUPUESTO_FACTURAR')->count());
    }

    public function test_no_se_puede_vender_dos_veces_el_mismo_presupuesto(): void
    {
        $p = $this->directo('ACEPTADO', 5);
        $this->vender($p)->assertOk();

        $r = $this->vender($p);
        $r->assertStatus(422)->assertJson(['success' => false]);
        $this->assertEquals(9, $this->stock(1));
        $this->assertEquals(1, DB::table('ventas')->count());
    }

    public function test_una_venta_que_falla_no_marca_el_presupuesto_como_vendido(): void
    {
        $p = $this->directo('ACEPTADO', 5);

        $this->vender($p, [['pro_id' => 1, 'cantidad' => 999]])->assertStatus(422);

        $this->assertEquals('ACEPTADO', $p->fresh()->pre_estado);
        $this->assertNull($p->fresh()->vta_id);
        $this->assertEquals(0, DB::table('ventas')->count());
    }

    public function test_no_se_vende_un_presupuesto_vencido_ni_rechazado(): void
    {
        $vencido = $this->directo('ACEPTADO', -1);
        $rechazado = $this->directo('RECHAZADO', 5);

        $this->vender($vencido)->assertStatus(422);
        $this->vender($rechazado)->assertStatus(422);

        $this->assertEquals(0, DB::table('ventas')->count());
        $this->assertEquals(10, $this->stock(1));
    }

    public function test_se_puede_vender_un_borrador_o_enviado_dentro_del_plazo(): void
    {
        $this->vender($this->directo('BORRADOR', 2))->assertOk();
        $this->vender($this->directo('ENVIADO', 0))->assertOk();
        $this->assertEquals(8, $this->stock(1));
    }

    public function test_venta_normal_sin_presupuesto_sigue_funcionando(): void
    {
        $r = $this->actingAs($this->cajero)->postJson('/pdv/store', [
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1,
            'carrito' => [['pro_id' => 1, 'cantidad' => 1]],
        ]);

        $r->assertOk();
        $this->assertEquals(9, $this->stock(1));
    }

    public function test_pdv_precarga_el_presupuesto_y_avisa_si_no_se_puede_vender(): void
    {
        $ok = $this->directo('ACEPTADO', 5);
        $r = $this->actingAs($this->cajero)->get('/pdv?presupuesto='.$ok->pre_id);
        $r->assertOk();
        $this->assertEquals($ok->pre_id, $r->viewData('precarga')['id']);
        $this->assertCount(1, $r->viewData('precarga')['items']);

        $vencido = $this->directo('ACEPTADO', -2);
        $r = $this->actingAs($this->cajero)->get('/pdv?presupuesto='.$vencido->pre_id);
        $r->assertOk();
        $this->assertNull($r->viewData('precarga'));
        $r->assertSessionHas('error');
    }

    public function test_el_detalle_avisa_si_cambio_el_precio(): void
    {
        $p = $this->crear();
        DB::table('productos')->where('pro_id', 1)->update(['pro_precioventa' => 12000]);

        $r = $this->actingAs($this->vendedor)->get("/presupuestos/{$p->pre_id}");
        $r->assertOk();
        $this->assertArrayHasKey(1, $r->viewData('cambios'));
    }

    public function test_pantallas_de_formulario_cargan(): void
    {
        $p = $this->crear();

        $this->actingAs($this->vendedor)->get('/presupuestos/nuevo')->assertOk()->assertSee('Nuevo presupuesto');
        $this->actingAs($this->vendedor)->get("/presupuestos/{$p->pre_id}/editar")->assertOk()->assertSee($p->numero);
        $this->actingAs($this->vendedor)->get("/presupuestos/{$p->pre_id}")->assertOk()->assertSee('Convertir en venta');
    }

    private function tablaVieja(bool $conFila): void
    {
        \Illuminate\Support\Facades\Schema::dropIfExists('presupuestos');
        DB::statement('CREATE TABLE presupuestos (pre_id INTEGER PRIMARY KEY AUTOINCREMENT, suc_id INTEGER NOT NULL, cli_id INTEGER NOT NULL, usu_id INTEGER NOT NULL, pre_fecha TIMESTAMP, pre_validez_hasta DATE, pre_total NUMERIC NOT NULL, pre_estado VARCHAR(20) DEFAULT \'PENDIENTE\')');
        if ($conFila) {
            DB::table('presupuestos')->insert(['suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'pre_total' => 10, 'pre_estado' => 'PENDIENTE']);
        }
    }

    public function test_tabla_presupuestos_vieja_y_vacia_se_reemplaza_y_la_pantalla_carga(): void
    {
        $this->tablaVieja(false);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento'));

        (require database_path('migrations/2026_10_13_000002_parte6_corregir_presupuestos.php'))->up();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento'));
        $this->actingAs($this->vendedor)->get('/presupuestos')->assertOk();
        // segunda corrida: no hace nada
        (require database_path('migrations/2026_10_13_000002_parte6_corregir_presupuestos.php'))->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento'));
    }

    public function test_tabla_presupuestos_vieja_con_datos_no_se_borra_en_silencio(): void
    {
        $this->tablaVieja(true);

        $this->expectException(\RuntimeException::class);
        (require database_path('migrations/2026_10_13_000002_parte6_corregir_presupuestos.php'))->up();
    }

    public function test_migracion_original_tambien_reemplaza_la_tabla_vieja_vacia(): void
    {
        $this->tablaVieja(false);

        (require database_path('migrations/2026_10_13_000001_parte6_presupuestos.php'))->up();

        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento'));
    }
}
