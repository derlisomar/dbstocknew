<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\ConfiguracionService as Cfg;
use App\Services\LicenciaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 8: panel del vendedor (acceso privado, negocio, ediciones y módulos, estructura, planes, licencia y pagos).
 * Ejecutar:  php artisan test --filter=Parte8Test
 */
class Parte8Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $admin;
    private User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        config(['vendedor.clave_hash' => Hash::make('secreta-1234')]);

        $rolAdmin = DB::table('roles')->insertGetId(['rol_nombre' => 'Administrador'], 'rol_id');
        $rolCaja = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero'], 'rol_id');
        foreach (['PDV_USAR', 'PRESUPUESTOS_GESTIONAR'] as $codigo) {
            $permId = DB::table('permisos')->insertGetId(['perm_codigo' => $codigo], 'perm_id');
            DB::table('rol_permisos')->insert(['rol_id' => $rolCaja, 'perm_id' => $permId]);
        }
        $this->admin = User::create(['rol_id' => $rolAdmin, 'usu_usuario' => 'admin', 'usu_email' => 'a@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
        $this->cajero = User::create(['rol_id' => $rolCaja, 'usu_usuario' => 'caja', 'usu_email' => 'c@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        DB::table('sucursales')->insert(['suc_id' => 1, 'suc_nombre' => 'Central', 'suc_factura_secuencia' => 1, 'suc_activa' => true]);
        DB::table('cajas')->insert(['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE', 'caj_activa' => true]);
        DB::table('caja_sesiones')->insert(['ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA']);
        DB::table('clientes')->insert(['cli_id' => 1, 'cli_nombre' => 'Consumidor', 'cli_ruc_ci' => '1', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 1, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0]);
        DB::table('categorias')->insert(['cat_id' => 1, 'cat_nombre' => 'General']);
        DB::table('productos')->insert(['pro_id' => 1, 'cat_id' => 1, 'pro_codigo' => 'A1', 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_preciomayorista' => 0, 'pro_preciocosto' => 6000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10]);
        $this->ajustarSecuencias();
    }

    // ------------------------------------------------------------------ ayudas

    private function vendedor()
    {
        return $this->withSession(['vendedor_hasta' => time() + 3600]);
    }

    private function hoy(): \Carbon\Carbon
    {
        return LicenciaService::hoy();
    }

    private function vencimiento(int $dias, array $extra = []): void
    {
        Cfg::set(array_merge(['lic_tipo' => 'SUSCRIPCION', 'lic_vence' => $this->hoy()->addDays($dias)->toDateString()], $extra));
    }

    private function vender(array $extra = [])
    {
        return $this->actingAs($this->cajero)->postJson('/pdv/store', array_merge([
            'cli_id' => 1, 'vta_tipo' => 'CONTADO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1,
            'carrito' => [['pro_id' => 1, 'cantidad' => 1]],
        ], $extra));
    }

    // ------------------------------------------------------------------ acceso

    public function test_sin_clave_configurada_el_panel_no_existe(): void
    {
        config(['vendedor.clave_hash' => null]);

        $this->get(route('vendedor.login'))->assertNotFound();
        $this->get(route('vendedor.resumen'))->assertNotFound();
    }

    public function test_hay_que_entrar_con_la_clave(): void
    {
        $this->get(route('vendedor.resumen'))->assertRedirect(route('vendedor.login'));
        $this->get(route('vendedor.licencia'))->assertRedirect(route('vendedor.login'));

        $this->post(route('vendedor.entrar'), ['clave' => 'mala'])->assertSessionHas('error');
        $this->get(route('vendedor.resumen'))->assertRedirect(route('vendedor.login'));

        $this->post(route('vendedor.entrar'), ['clave' => 'secreta-1234'])->assertRedirect(route('vendedor.resumen'));
        $this->get(route('vendedor.resumen'))->assertOk();
    }

    public function test_el_administrador_del_negocio_no_entra_al_panel(): void
    {
        $this->actingAs($this->admin)->get(route('vendedor.resumen'))->assertRedirect(route('vendedor.login'));
        $this->actingAs($this->admin)->post(route('vendedor.licencia.pagos'), ['monto' => 1])->assertRedirect(route('vendedor.login'));
    }

    public function test_se_bloquea_despues_de_cinco_intentos_fallidos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('vendedor.entrar'), ['clave' => 'mala']);
        }

        $this->post(route('vendedor.entrar'), ['clave' => 'secreta-1234'])->assertSessionHas('error');
        $this->get(route('vendedor.resumen'))->assertRedirect(route('vendedor.login'));
    }

    public function test_la_sesion_del_panel_vence(): void
    {
        $this->withSession(['vendedor_hasta' => time() - 5])->get(route('vendedor.resumen'))->assertRedirect(route('vendedor.login'));
    }

    // ------------------------------------------------------------------ negocio

    public function test_guarda_los_datos_del_negocio_y_se_ven_en_el_sistema(): void
    {
        $this->vendedor()->post(route('vendedor.negocio.guardar'), [
            'negocio_nombre' => 'Ferretería El Tornillo', 'negocio_ruc' => '80099999-1', 'negocio_pie_ticket' => 'Vuelva pronto',
        ])->assertSessionHas('success');

        $this->assertEquals('Ferretería El Tornillo', Cfg::nombreNegocio());
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Ferretería El Tornillo');
    }

    public function test_el_nombre_del_negocio_es_obligatorio(): void
    {
        $this->vendedor()->post(route('vendedor.negocio.guardar'), ['negocio_nombre' => ''])->assertSessionHasErrors('negocio_nombre');
    }

    public function test_sube_y_quita_el_logo(): void
    {
        $carpeta = storage_path('framework/testing/negocio-'.uniqid());
        config(['vendedor.carpeta_logo' => $carpeta]);

        try {
            $this->vendedor()->post(route('vendedor.negocio.guardar'), [
                'negocio_nombre' => 'Mi Negocio', 'logo' => UploadedFile::fake()->image('logo.png', 100, 100),
            ])->assertSessionHas('success');
            $this->assertCount(1, File::glob($carpeta.'/logo.*'));
            $this->assertNotNull(Cfg::logoUrl());

            $this->vendedor()->post(route('vendedor.negocio.guardar'), ['negocio_nombre' => 'Mi Negocio', 'quitar_logo' => 1]);
            $this->assertCount(0, File::glob($carpeta.'/logo.*'));
            $this->assertNull(Cfg::logoUrl());

            $this->vendedor()->post(route('vendedor.negocio.guardar'), [
                'negocio_nombre' => 'Mi Negocio', 'logo' => UploadedFile::fake()->create('virus.php', 10, 'application/x-php'),
            ])->assertSessionHasErrors('logo');
        } finally {
            File::deleteDirectory($carpeta);
        }
    }

    public function test_los_comprobantes_usan_el_nombre_del_negocio(): void
    {
        $this->vender()->assertOk();
        $ventaId = DB::table('ventas')->value('vta_id');

        $this->actingAs($this->cajero)->get("/pdv/ticket-simple/{$ventaId}")->assertOk()->assertSee('MI EMPRESA S.A.');

        Cfg::set(['negocio_nombre' => 'Almacén Don José', 'negocio_ruc' => '80011223-4']);
        $this->actingAs($this->cajero)->get("/pdv/ticket-simple/{$ventaId}")->assertOk()->assertSee('Almacén Don José')->assertSee('80011223-4')->assertDontSee('MI EMPRESA S.A.');
    }

    // ------------------------------------------------------------------ ediciones y módulos

    public function test_sin_configuracion_el_sistema_sigue_como_siempre(): void
    {
        $this->assertEquals('COMPLETA', Cfg::edicion());
        foreach (array_keys(config('modulos.catalogo')) as $m) {
            $this->assertTrue(Cfg::modulo($m), $m);
        }
        $this->assertEquals('SIN_LICENCIA', LicenciaService::estado()['estado']);
        $this->assertFalse(LicenciaService::estado()['bloquea']);
        $this->assertEquals(0, Cfg::limite('sucursales'));
    }

    public function test_edicion_basica_deja_solo_lo_reducido(): void
    {
        $this->vendedor()->post(route('vendedor.modulos.edicion'), ['edicion' => 'BASICA'])->assertSessionHas('success');

        foreach (['cobranzas', 'presupuestos', 'inventario'] as $m) {
            $this->assertTrue(Cfg::modulo($m), $m);
        }
        foreach (['compras', 'promociones', 'reportes_avanzados', 'depositos', 'auditoria', 'multimoneda'] as $m) {
            $this->assertFalse(Cfg::modulo($m), $m);
        }
    }

    public function test_un_modulo_apagado_no_se_puede_abrir_ni_escribiendo_la_direccion(): void
    {
        $this->vendedor()->post(route('vendedor.modulos.edicion'), ['edicion' => 'BASICA']);

        foreach (['/compras', '/cuentas-pagar', '/promociones', '/depositos', '/auditoria', '/operaciones/reporte-abc', '/proveedores'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertRedirect(route('dashboard'));
        }
        $this->actingAs($this->admin)->post('/compras', [])->assertRedirect(route('dashboard'));

        // Lo incluido sigue funcionando.
        foreach (['/presupuestos', '/inventario', '/cobranzas', '/pdv', '/dashboard'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_activar_un_modulo_suelto_en_la_edicion_basica(): void
    {
        $this->vendedor()->post(route('vendedor.modulos.edicion'), ['edicion' => 'BASICA']);
        $this->vendedor()->post(route('vendedor.modulos.guardar'), ['modulos' => ['cobranzas', 'presupuestos', 'inventario', 'compras']])->assertSessionHas('success');

        $this->assertTrue(Cfg::modulo('compras'));
        $this->assertFalse(Cfg::modulo('promociones'));
        $this->actingAs($this->admin)->get('/compras')->assertOk();
    }

    public function test_el_menu_oculta_lo_que_el_plan_no_incluye(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('Compras y Proveedores')->assertSee('Cuentas a pagar');

        $this->vendedor()->post(route('vendedor.modulos.edicion'), ['edicion' => 'BASICA']);

        $this->actingAs($this->admin)->get('/dashboard')->assertDontSee('Compras y Proveedores')->assertDontSee('Cuentas a pagar')->assertSee('Presupuestos');
    }

    public function test_pdv_respeta_moneda_y_credito_del_plan(): void
    {
        $this->vendedor()->post(route('vendedor.modulos.edicion'), ['edicion' => 'BASICA']);

        $this->vender(['moneda' => 'USD'])->assertStatus(422)->assertJsonPath('message', 'Este negocio solo vende en guaraníes.');
        $this->vender(['vta_tipo' => 'CREDITO'])->assertOk(); // la edición básica incluye cobranzas

        $this->vendedor()->post(route('vendedor.modulos.guardar'), ['modulos' => ['presupuestos']]);
        $this->vender(['vta_tipo' => 'CREDITO'])->assertStatus(422)->assertJsonPath('message', 'Este negocio no tiene habilitada la venta a crédito.');
        $this->vender()->assertOk();
    }

    public function test_limites_del_plan(): void
    {
        $this->vendedor()->post(route('vendedor.modulos.guardar'), ['modulos' => ['cobranzas'], 'limite_sucursales' => 1, 'limite_cajas' => 1, 'limite_usuarios' => 2]);

        $this->actingAs($this->admin)->post('/sucursales', ['suc_nombre' => 'Otra'])->assertSessionHas('error');
        $this->assertEquals(1, DB::table('sucursales')->count());

        $this->actingAs($this->admin)->post('/cajas', ['suc_id' => 1, 'caj_nombre' => 'Caja 2'])->assertSessionHas('error');
        $this->assertEquals(1, DB::table('cajas')->count());

        $this->actingAs($this->admin)->post('/usuarios', [
            'usu_nombre' => 'A', 'usu_apellido' => 'B', 'usu_cedula' => '9', 'usu_usuario' => 'nuevo', 'usu_email' => 'n@x.com', 'usu_password' => 'secreta1', 'rol_id' => 2,
        ])->assertSessionHas('error');
        $this->assertEquals(0, DB::table('usuarios')->where('usu_usuario', 'nuevo')->count());

        // Con el límite en 0 (sin límite) se puede crear.
        $this->vendedor()->post(route('vendedor.modulos.guardar'), ['modulos' => ['cobranzas']]);
        $this->actingAs($this->admin)->post('/sucursales', ['suc_nombre' => 'Otra'])->assertRedirect();
        $this->assertEquals(2, DB::table('sucursales')->count());
    }

    // ------------------------------------------------------------------ licencia

    public function test_estados_de_la_licencia_segun_la_fecha(): void
    {
        $this->vencimiento(30);
        $this->assertEquals('ACTIVA', LicenciaService::estado()['estado']);

        $this->vencimiento(3);
        $e = LicenciaService::estado();
        $this->assertEquals('POR_VENCER', $e['estado']);
        $this->assertFalse($e['bloquea']);

        $this->vencimiento(0);
        $this->assertEquals('POR_VENCER', LicenciaService::estado()['estado']);

        $this->vencimiento(-1, ['lic_gracia_dias' => 5]);
        $e = LicenciaService::estado();
        $this->assertEquals('GRACIA', $e['estado']);
        $this->assertFalse($e['bloquea']);

        $this->vencimiento(-5, ['lic_gracia_dias' => 5]);
        $this->assertEquals('GRACIA', LicenciaService::estado()['estado']);

        $this->vencimiento(-6, ['lic_gracia_dias' => 5]);
        $e = LicenciaService::estado();
        $this->assertEquals('SOLO_LECTURA', $e['estado']);
        $this->assertTrue($e['bloquea']);
    }

    public function test_en_gracia_se_sigue_vendiendo_con_aviso(): void
    {
        $this->vencimiento(-2, ['lic_gracia_dias' => 5]);

        $this->vender()->assertOk();
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('Tu plan venció');
    }

    public function test_en_solo_lectura_se_consulta_todo_pero_no_se_registra(): void
    {
        $this->vencimiento(-10, ['lic_gracia_dias' => 5]);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('solo lectura');
        $this->actingAs($this->admin)->get('/operaciones/ventas')->assertOk();
        $this->actingAs($this->admin)->get('/presupuestos')->assertOk();

        $this->vender()->assertStatus(423)->assertJsonPath('success', false);
        $this->assertEquals(0, DB::table('ventas')->count());
        $this->assertEquals(10, DB::table('productos')->where('pro_id', 1)->value('pro_stockactual'));

        $this->actingAs($this->admin)->post('/sucursales', ['suc_nombre' => 'X'])->assertSessionHas('error');
        $this->assertEquals(1, DB::table('sucursales')->count());
    }

    public function test_en_solo_lectura_se_puede_cerrar_sesion_y_cerrar_caja(): void
    {
        $this->vencimiento(-10, ['lic_gracia_dias' => 5]);

        $this->actingAs($this->cajero)->post(route('logout'))->assertRedirect();

        $r = $this->actingAs($this->cajero)->post(route('finanzas.cerrarCaja', 1), []);
        $this->assertNotEquals(423, $r->getStatusCode());
        $r->assertSessionMissing('error', LicenciaService::estado()['mensaje']);
    }

    public function test_el_panel_del_vendedor_funciona_aunque_el_negocio_este_bloqueado(): void
    {
        $this->vencimiento(-10, ['lic_gracia_dias' => 5]);

        $this->vendedor()->post(route('vendedor.licencia.pagos'), ['monto' => 100000, 'meses' => 1])->assertSessionHas('success');
        $this->assertFalse(LicenciaService::estado()['bloquea']);
        $this->vender()->assertOk();
    }

    public function test_la_suspension_manual_pasa_a_solo_lectura_y_el_pago_la_levanta(): void
    {
        $this->vencimiento(30);
        $this->vendedor()->post(route('vendedor.licencia.ajustes'), ['lic_tipo' => 'SUSCRIPCION', 'lic_vence' => $this->hoy()->addDays(30)->toDateString(), 'lic_gracia_dias' => 5, 'lic_suspendida' => 1]);

        $this->assertTrue(LicenciaService::estado()['bloquea']);
        $this->vender()->assertStatus(423);

        $this->vendedor()->post(route('vendedor.licencia.pagos'), ['monto' => 0, 'meses' => 1]);
        $this->assertFalse(LicenciaService::estado()['bloquea']);
    }

    // ------------------------------------------------------------------ pagos

    public function test_pago_antes_de_vencer_suma_desde_el_vencimiento(): void
    {
        $this->vencimiento(10);
        $pago = LicenciaService::registrarPago(['monto' => 150000, 'meses' => 1]);

        $this->assertEquals($this->hoy()->addDays(10)->addMonthNoOverflow()->toDateString(), $pago->lpa_hasta->toDateString());
        $this->assertEquals($pago->lpa_hasta->toDateString(), Cfg::get('lic_vence'));
    }

    public function test_pago_en_gracia_no_pierde_dias_y_pago_tarde_cuenta_desde_hoy(): void
    {
        $this->vencimiento(-3, ['lic_gracia_dias' => 5]);
        $vencido = $this->hoy()->subDays(3);
        $pago = LicenciaService::registrarPago(['monto' => 150000, 'meses' => 1]);
        $this->assertEquals($vencido->copy()->addMonthNoOverflow()->toDateString(), $pago->lpa_hasta->toDateString());

        $this->vencimiento(-30, ['lic_gracia_dias' => 5]);
        $pago = LicenciaService::registrarPago(['monto' => 150000, 'meses' => 1]);
        $this->assertEquals($this->hoy()->addMonthNoOverflow()->toDateString(), $pago->lpa_hasta->toDateString());
        $this->assertFalse(LicenciaService::estado()['bloquea']);
    }

    public function test_pago_unico_deja_la_licencia_permanente(): void
    {
        $this->vencimiento(-30, ['lic_gracia_dias' => 5]);
        $pago = LicenciaService::registrarPago(['monto' => 5000000, 'meses' => 0]);

        $this->assertNull($pago->lpa_hasta);
        $e = LicenciaService::estado();
        $this->assertEquals('ACTIVA', $e['estado']);
        $this->assertEquals('PERPETUA', $e['tipo']);
        $this->assertNull(Cfg::get('lic_vence'));
    }

    public function test_anular_el_ultimo_pago_devuelve_el_vencimiento_anterior(): void
    {
        $this->vencimiento(10);
        $anterior = Cfg::get('lic_vence');
        $primero = LicenciaService::registrarPago(['monto' => 100000, 'meses' => 1]);
        $segundo = LicenciaService::registrarPago(['monto' => 100000, 'meses' => 1]);

        try {
            LicenciaService::anularPago($primero->lpa_id, 'error');
            $this->fail('No debería dejar anular uno que no es el último');
        } catch (\App\Exceptions\NegocioException) {
            $this->assertTrue(true);
        }

        LicenciaService::anularPago($segundo->lpa_id, 'se cargó dos veces');
        $this->assertEquals($primero->lpa_hasta->toDateString(), Cfg::get('lic_vence'));

        LicenciaService::anularPago($primero->lpa_id, 'error de monto');
        $this->assertEquals($anterior, Cfg::get('lic_vence'));
        $this->assertEquals(2, DB::table('licencia_pagos')->where('lpa_estado', 'ANULADO')->count());
    }

    public function test_pagos_desde_el_panel_y_validaciones(): void
    {
        $this->vendedor()->post(route('vendedor.licencia.pagos'), ['monto' => ''])->assertSessionHasErrors('monto');
        $this->vendedor()->post(route('vendedor.licencia.pagos'), ['monto' => -5])->assertSessionHasErrors('monto');
        $this->vendedor()->post(route('vendedor.licencia.pagos'), ['monto' => 150000, 'meses' => 3, 'forma' => 'Transferencia', 'referencia' => 'T-1'])->assertSessionHas('success');

        $this->assertEquals(1, DB::table('licencia_pagos')->count());
        $this->vendedor()->get(route('vendedor.licencia'))->assertOk()->assertSee('Transferencia');
    }

    // ------------------------------------------------------------------ planes

    public function test_asignar_un_plan_aplica_edicion_modulos_y_limites(): void
    {
        $basico = Plan::where('plan_nombre', 'Básico')->first();
        $this->vendedor()->post(route('vendedor.licencia.plan'), ['plan_id' => $basico->plan_id])->assertSessionHas('success');

        $this->assertEquals('BASICA', Cfg::edicion());
        $this->assertFalse(Cfg::modulo('compras'));
        $this->assertEquals(1, Cfg::limite('sucursales'));
        $this->assertEquals(2, Cfg::limite('cajas'));
        $this->assertEquals(3, Cfg::limite('usuarios'));
        $this->assertEquals('SUSCRIPCION', Cfg::get('lic_tipo'));

        $completo = Plan::where('plan_nombre', 'Completo')->first();
        $this->vendedor()->post(route('vendedor.licencia.plan'), ['plan_id' => $completo->plan_id]);
        $this->assertTrue(Cfg::modulo('compras'));
        $this->assertEquals(0, Cfg::limite('sucursales'));
    }

    public function test_crear_y_editar_planes_con_modulos_extra(): void
    {
        $this->vendedor()->post(route('vendedor.planes.crear'), [
            'plan_nombre' => 'Básico + Compras', 'plan_edicion' => 'BASICA', 'plan_modulos' => ['compras'],
            'plan_precio' => 200000, 'plan_meses' => 1, 'plan_max_sucursales' => 2,
        ])->assertSessionHas('success');

        $plan = Plan::where('plan_nombre', 'Básico + Compras')->first();
        $this->assertEquals(['compras'], $plan->modulosExtra());

        $this->vendedor()->post(route('vendedor.licencia.plan'), ['plan_id' => $plan->plan_id]);
        $this->assertTrue(Cfg::modulo('compras'));
        $this->assertFalse(Cfg::modulo('promociones'));

        $this->vendedor()->put(route('vendedor.planes.editar', $plan->plan_id), [
            'plan_nombre' => 'Básico + Compras', 'plan_edicion' => 'BASICA', 'plan_precio' => 250000, 'plan_meses' => 12,
        ])->assertSessionHas('success');
        $this->assertEquals(250000, (float) $plan->fresh()->plan_precio);
        $this->assertEquals([], $plan->fresh()->modulosExtra());

        $this->vendedor()->post(route('vendedor.planes.estado', $plan->plan_id));
        $this->assertFalse((bool) $plan->fresh()->plan_activo);
    }

    public function test_plan_invalido_se_rechaza(): void
    {
        $this->vendedor()->post(route('vendedor.planes.crear'), ['plan_nombre' => '', 'plan_edicion' => 'OTRA', 'plan_precio' => -1, 'plan_meses' => 99])
            ->assertSessionHasErrors(['plan_nombre', 'plan_edicion', 'plan_precio', 'plan_meses']);
        $this->vendedor()->post(route('vendedor.licencia.plan'), ['plan_id' => 999])->assertSessionHasErrors('plan_id');
    }

    // ------------------------------------------------------------------ estructura y usuarios

    public function test_crea_sucursal_caja_y_deposito_desde_el_panel(): void
    {
        $this->vendedor()->post(route('vendedor.sucursales.crear'), ['suc_nombre' => 'Sucursal Norte', 'suc_direccion' => 'Av. 1', 'suc_timbrado' => '123', 'suc_timbrado_inicio' => '2026-01-01', 'suc_timbrado_fin' => '2027-01-01'])->assertSessionHas('success');
        $sucId = DB::table('sucursales')->where('suc_nombre', 'Sucursal Norte')->value('suc_id');
        $this->assertNotNull($sucId);

        $this->vendedor()->post(route('vendedor.cajas.crear'), ['suc_id' => $sucId, 'caj_nombre' => 'Caja Norte', 'caj_tipo_impresion' => 'TICKET_FACTURA'])->assertSessionHas('success');
        $this->assertEquals('TICKET_FACTURA', DB::table('cajas')->where('caj_nombre', 'Caja Norte')->value('caj_tipo_impresion'));

        $this->vendedor()->post(route('vendedor.depositos.crear'), ['suc_id' => $sucId, 'dep_nombre' => 'Depósito Norte'])->assertSessionHas('success');
        $this->assertEquals(1, DB::table('depositos')->count());

        $this->vendedor()->get(route('vendedor.estructura'))->assertOk()->assertSee('Sucursal Norte')->assertSee('Caja Norte');
    }

    public function test_estructura_rechaza_datos_invalidos(): void
    {
        $this->vendedor()->post(route('vendedor.sucursales.crear'), ['suc_nombre' => ''])->assertSessionHasErrors('suc_nombre');
        $this->vendedor()->post(route('vendedor.cajas.crear'), ['suc_id' => 999, 'caj_nombre' => 'X', 'caj_tipo_impresion' => 'RARO'])->assertSessionHasErrors(['suc_id', 'caj_tipo_impresion']);
        $this->vendedor()->post(route('vendedor.sucursales.crear'), ['suc_nombre' => 'A', 'suc_timbrado_inicio' => '2026-05-01', 'suc_timbrado_fin' => '2026-01-01'])->assertSessionHasErrors('suc_timbrado_fin');
    }

    public function test_activar_y_desactivar(): void
    {
        $this->vendedor()->post(route('vendedor.sucursales.estado', 1));
        $this->assertFalse((bool) DB::table('sucursales')->where('suc_id', 1)->value('suc_activa'));
        $this->vendedor()->post(route('vendedor.sucursales.estado', 1));
        $this->assertTrue((bool) DB::table('sucursales')->where('suc_id', 1)->value('suc_activa'));

        $this->vendedor()->post(route('vendedor.cajas.estado', 1));
        $this->assertFalse((bool) DB::table('cajas')->where('caj_id', 1)->value('caj_activa'));
        $this->vendedor()->post(route('vendedor.cajas.estado', 999))->assertNotFound();
    }

    public function test_la_cotizacion_deja_una_sola_activa(): void
    {
        $this->vendedor()->post(route('vendedor.cotizacion'), ['cot_dolar' => 7500, 'cot_real' => 1400]);
        $this->vendedor()->post(route('vendedor.cotizacion'), ['cot_dolar' => 7600, 'cot_real' => 1450]);

        $this->assertEquals(2, DB::table('cotizaciones')->count());
        $this->assertEquals(1, DB::table('cotizaciones')->where('cot_activa', true)->count());
        $this->assertEquals(7600, (float) DB::table('cotizaciones')->where('cot_activa', true)->value('cot_dolar'));
        $this->vendedor()->post(route('vendedor.cotizacion'), ['cot_dolar' => 0, 'cot_real' => 1])->assertSessionHasErrors('cot_dolar');
    }

    public function test_crea_el_administrador_y_restablece_claves(): void
    {
        $this->vendedor()->post(route('vendedor.usuarios.administrador'), [
            'usu_nombre' => 'Ana', 'usu_apellido' => 'Gómez', 'usu_cedula' => '123456', 'usu_usuario' => 'ana', 'usu_email' => 'ana@negocio.com', 'usu_password' => 'clave-segura',
        ])->assertSessionHas('success');

        $ana = User::where('usu_usuario', 'ana')->first();
        $this->assertEquals('Administrador', $ana->rol->rol_nombre);
        $this->assertTrue($ana->esAdministrador());
        $this->assertTrue(Hash::check('clave-segura', $ana->usu_password));

        $this->vendedor()->post(route('vendedor.usuarios.administrador'), [
            'usu_nombre' => 'X', 'usu_apellido' => 'Y', 'usu_cedula' => '123456', 'usu_usuario' => 'ana', 'usu_email' => 'ana@negocio.com', 'usu_password' => 'corta',
        ])->assertSessionHasErrors(['usu_cedula', 'usu_usuario', 'usu_email', 'usu_password']);

        $this->vendedor()->post(route('vendedor.usuarios.clave', $ana->usu_id), ['clave' => 'otra-clave-nueva'])->assertSessionHas('success');
        $this->assertTrue(Hash::check('otra-clave-nueva', $ana->fresh()->usu_password));
        $this->vendedor()->post(route('vendedor.usuarios.clave', $ana->usu_id), ['clave' => '123'])->assertSessionHasErrors('clave');
    }

    // ------------------------------------------------------------------ resumen, herramientas, auditoría

    public function test_la_lista_de_preparacion_marca_lo_que_falta_y_lo_que_esta(): void
    {
        $r = $this->vendedor()->get(route('vendedor.resumen'));
        $r->assertOk();
        $lista = collect($r->viewData('lista'));
        $this->assertFalse($lista->firstWhere('titulo', 'Datos del negocio')['ok']);
        $this->assertTrue($lista->firstWhere('titulo', 'Al menos una sucursal')['ok']);
        $this->assertTrue($lista->firstWhere('titulo', 'Al menos una caja')['ok']);
        $this->assertTrue($lista->firstWhere('titulo', 'Usuario administrador del negocio')['ok']);

        Cfg::set(['negocio_nombre' => 'Listo SA']);
        $r = $this->vendedor()->get(route('vendedor.resumen'));
        $this->assertTrue(collect($r->viewData('lista'))->firstWhere('titulo', 'Datos del negocio')['ok']);
        $this->assertGreaterThan($lista->where('ok', true)->count(), $r->viewData('hechos'));
    }

    public function test_todas_las_pantallas_del_panel_cargan(): void
    {
        foreach (['resumen', 'negocio', 'modulos', 'estructura', 'usuarios', 'planes', 'licencia', 'herramientas'] as $pantalla) {
            $this->vendedor()->get(route('vendedor.'.$pantalla))->assertOk();
        }
        $this->vendedor()->get(route('vendedor.planes', ['editar' => 1]))->assertOk()->assertSee('Editar plan');
    }

    public function test_herramienta_de_verificacion_y_cache(): void
    {
        $r = $this->vendedor()->post(route('vendedor.herramientas.verificar'));
        $r->assertRedirect();
        $this->assertStringContainsString('Panel del vendedor (Parte 8)', session('salida'));

        $this->vendedor()->post(route('vendedor.herramientas.cache'))->assertSessionHas('success');
    }

    public function test_las_acciones_del_vendedor_quedan_en_la_auditoria(): void
    {
        $this->vendedor()->post(route('vendedor.licencia.pagos'), ['monto' => 100000, 'meses' => 1]);
        $this->vendedor()->post(route('vendedor.modulos.edicion'), ['edicion' => 'BASICA']);

        $filas = DB::table('auditoria')->whereIn('aud_accion', ['LICENCIA_PAGO', 'VENDEDOR_EDICION'])->get();
        $this->assertCount(2, $filas);
        $this->assertNull($filas->first()->usu_id);
        $this->assertStringContainsString('vendedor', $filas->first()->aud_detalle);
    }

    public function test_la_migracion_se_puede_correr_dos_veces_sin_duplicar_planes(): void
    {
        (require database_path('migrations/2026_10_14_000001_parte8_panel_vendedor.php'))->up();

        $this->assertEquals(2, DB::table('planes')->count());
    }

    public function test_el_comando_de_clave_genera_la_linea_para_el_env(): void
    {
        $this->artisan('vendedor:clave')
            ->expectsQuestion('Escribí la clave que querés usar (mínimo 10 caracteres)', 'una-clave-larga-1')
            ->expectsQuestion('Repetila', 'una-clave-larga-1')
            ->expectsOutputToContain("VENDEDOR_CLAVE_HASH='")
            ->assertExitCode(0);

        $this->artisan('vendedor:clave')
            ->expectsQuestion('Escribí la clave que querés usar (mínimo 10 caracteres)', 'corta')
            ->assertExitCode(1);
    }

    public function test_presupuesto_impreso_lleva_el_nombre_del_negocio(): void
    {
        Cfg::set(['negocio_nombre' => 'Librería Central']);
        $id = DB::table('presupuestos')->insertGetId([
            'cli_id' => 1, 'usu_id' => $this->cajero->usu_id, 'pre_fecha' => now(), 'pre_validez_dias' => 7,
            'pre_fecha_vencimiento' => $this->hoy()->addDays(7)->toDateString(), 'pre_total' => 10000, 'pre_estado' => 'BORRADOR',
        ], 'pre_id');
        DB::table('detalle_presupuestos')->insert(['pre_id' => $id, 'pro_id' => 1, 'dpr_cantidad' => 1, 'dpr_precio' => 10000, 'dpr_subtotal' => 10000]);

        $this->actingAs($this->cajero)->get("/presupuestos/{$id}/imprimir")->assertOk()->assertSee('Librería Central');
    }
}
