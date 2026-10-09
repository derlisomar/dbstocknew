<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\RespaldoService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Parte 3: anulación de cobros, auditoría, cierres con diferencia, tickets, zona horaria, respaldos y control de producción.
 * Ejecutar:  php artisan test --filter=Parte3Test
 */
class Parte3Test extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private User $cajero;
    private User $otro;
    private User $admin;
    private User $supervisor;

    private const PERMISOS_CAJERO = ['PDV_USAR', 'CAJA_ABRIR_CERRAR', 'COBRANZAS_REGISTRAR', 'COBRANZAS_ANULAR', 'VENTAS_ANULAR'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        $this->cajero = $this->crearUsuario('caja1', self::PERMISOS_CAJERO, 'Cajero');
        $this->otro = $this->crearUsuario('caja2', ['PDV_USAR', 'CAJA_ABRIR_CERRAR', 'COBRANZAS_REGISTRAR'], 'Cajero 2');
        $this->admin = $this->crearUsuario('admin', [], 'Administrador');
        $this->supervisor = $this->crearUsuario('super', ['VENTAS_HISTORIAL', 'FINANZAS_VER', 'AUDITORIA_VER', 'COBRANZAS_REGISTRAR'], 'Supervisor');

        DB::table('sucursales')->insert([
            'suc_id' => 1, 'suc_nombre' => 'Central', 'suc_timbrado' => '12345678',
            'suc_timbrado_inicio' => '2020-01-01', 'suc_timbrado_fin' => '2099-12-31', 'suc_factura_secuencia' => 1,
        ]);
        DB::table('cajas')->insert([
            ['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1', 'caj_tipo_impresion' => 'TICKET_SIMPLE'],
            ['caj_id' => 2, 'suc_id' => 1, 'caj_nombre' => 'Caja 2', 'caj_tipo_impresion' => 'TICKET_SIMPLE'],
        ]);
        DB::table('caja_sesiones')->insert([
            'ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA', 'ses_monto_inicial_gs' => 100000,
        ]);
        DB::table('clientes')->insert([
            ['cli_id' => 1, 'cli_nombre' => 'Consumidor', 'cli_ruc_ci' => '1', 'cli_permitir_credito' => 0, 'cli_limite_credito' => 0],
            ['cli_id' => 4, 'cli_nombre' => 'Con credito', 'cli_ruc_ci' => '4', 'cli_permitir_credito' => 1, 'cli_limite_credito' => 500000],
        ]);
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_nombre' => 'Arroz', 'pro_precioventa' => 10000, 'pro_stockactual' => 10, 'pro_activo' => 1, 'pro_tipo_iva' => 10],
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

    /** Venta a crédito de 2 unidades (20.000) y devuelve el id de la cuenta por cobrar. */
    private function ventaACredito(): array
    {
        $r = $this->actingAs($this->cajero)->postJson('/pdv/store', [
            'cli_id' => 4, 'vta_tipo' => 'CREDITO', 'forma_pago' => 'EFECTIVO', 'moneda' => 'GS', 'caj_id' => 1,
            'carrito' => [['pro_id' => 1, 'cantidad' => 2]],
        ]);
        $r->assertOk();
        $vtaId = (int) $r->json('venta_id');

        return [$vtaId, (int) DB::table('cuentas_cobrar')->where('vta_id', $vtaId)->value('cred_id')];
    }

    private function cobrar(int $credId, float $monto, array $extra = [], ?User $usuario = null): int
    {
        $r = $this->actingAs($usuario ?? $this->cajero)->postJson('/cobranzas/store', array_merge(['cred_id' => $credId, 'monto_pagar' => $monto, 'caj_id' => 1], $extra));
        $r->assertOk();

        return (int) $r->json('cob_id');
    }

    private function anularCobro(int $cobId, string $motivo = 'Monto mal cargado', ?User $usuario = null)
    {
        return $this->actingAs($usuario ?? $this->cajero)->post("/cobranzas/$cobId/anular", ['motivo' => $motivo]);
    }

    private function saldoCaja(int $cajId = 1): float
    {
        return (float) DB::table('cajas')->where('caj_id', $cajId)->value('caj_saldo_gs');
    }

    private function cuenta(int $credId): object
    {
        return DB::table('cuentas_cobrar')->where('cred_id', $credId)->first();
    }

    // ------------------------------------------------------------------ anulación de cobros

    public function test_anular_un_cobro_en_efectivo_devuelve_la_deuda_y_el_dinero_sale_de_la_caja(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);

        $this->assertEquals(5000, $this->saldoCaja());
        $this->assertEquals(15000, $this->cuenta($credId)->cred_saldo_pendiente);

        $this->anularCobro($cobId)->assertRedirect()->assertSessionHas('success');

        $this->assertEquals(0, $this->saldoCaja());
        $c = $this->cuenta($credId);
        $this->assertEquals(20000, $c->cred_saldo_pendiente);
        $this->assertSame('PENDIENTE', $c->cred_estado);

        $cobro = DB::table('cobranzas')->where('cob_id', $cobId)->first();
        $this->assertSame('ANULADA', $cobro->cob_estado);
        $this->assertSame('Monto mal cargado', $cobro->cob_motivo_anulacion);
        $this->assertEquals($this->cajero->usu_id, $cobro->cob_anulada_por);
        $this->assertNotNull($cobro->cob_anulada_fecha);

        // El libro queda: un ingreso y un egreso del mismo cobro, y la auditoría lo registra.
        $this->assertSame(2, DB::table('caja_movimientos')->where('cob_id', $cobId)->count());
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'COBRO_ANULADO')->where('aud_registro_id', (string) $cobId)->count());
    }

    public function test_un_cobro_que_cancelo_toda_la_deuda_la_reabre_al_anularse(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 20000);
        $this->assertSame('PAGADA', $this->cuenta($credId)->cred_estado);

        $this->anularCobro($cobId)->assertSessionHas('success');

        $c = $this->cuenta($credId);
        $this->assertSame('PENDIENTE', $c->cred_estado);
        $this->assertEquals(20000, $c->cred_saldo_pendiente);
    }

    public function test_anular_un_cobro_con_tarjeta_no_toca_el_efectivo(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 8000, ['forma_pago' => 'TARJETA_DEBITO']);
        $this->assertEquals(0, $this->saldoCaja());

        $this->anularCobro($cobId)->assertSessionHas('success');

        $this->assertEquals(0, $this->saldoCaja());
        $egreso = DB::table('caja_movimientos')->where('cob_id', $cobId)->where('mov_tipo', 'EGRESO')->first();
        $this->assertSame('TARJETA_DEBITO', $egreso->mov_forma_pago);
        $this->assertEquals(20000, $this->cuenta($credId)->cred_saldo_pendiente);
    }

    public function test_el_motivo_es_obligatorio_y_un_cobro_no_se_anula_dos_veces(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);

        $this->actingAs($this->cajero)->post("/cobranzas/$cobId/anular", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->assertSame('ACTIVA', DB::table('cobranzas')->where('cob_id', $cobId)->value('cob_estado'));

        $this->anularCobro($cobId)->assertSessionHas('success');
        $this->anularCobro($cobId)->assertSessionHas('error');

        // La deuda no se infló por el segundo intento.
        $this->assertEquals(20000, $this->cuenta($credId)->cred_saldo_pendiente);
        $this->assertSame(2, DB::table('caja_movimientos')->where('cob_id', $cobId)->count());
    }

    public function test_sin_permiso_no_se_puede_anular_un_cobro(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);

        $this->anularCobro($cobId, 'Intento', $this->otro)->assertForbidden();
        $this->assertSame('ACTIVA', DB::table('cobranzas')->where('cob_id', $cobId)->value('cob_estado'));
        $this->assertEquals(15000, $this->cuenta($credId)->cred_saldo_pendiente);
    }

    public function test_con_la_caja_del_cobro_cerrada_la_salida_sale_de_la_caja_propia_del_que_anula(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);

        DB::table('caja_sesiones')->where('ses_id', 1)->update(['ses_estado' => 'CERRADA']);

        // Sin caja propia abierta: se rechaza y no cambia nada.
        $this->anularCobro($cobId)->assertSessionHas('error');
        $this->assertSame('ACTIVA', DB::table('cobranzas')->where('cob_id', $cobId)->value('cob_estado'));
        $this->assertEquals(15000, $this->cuenta($credId)->cred_saldo_pendiente);

        // Con una caja propia abierta, el egreso se anota ahí.
        DB::table('caja_sesiones')->insert(['ses_id' => 2, 'caj_id' => 2, 'usu_id' => $this->cajero->usu_id, 'ses_estado' => 'ABIERTA', 'ses_monto_inicial_gs' => 0]);
        $this->anularCobro($cobId)->assertSessionHas('success');

        $this->assertSame(2, (int) DB::table('caja_movimientos')->where('cob_id', $cobId)->where('mov_tipo', 'EGRESO')->value('ses_id'));
    }

    public function test_tras_anular_el_cobro_se_puede_anular_la_venta_a_credito(): void
    {
        [$vtaId, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);

        // Con un cobro vigente, la venta no se puede anular (regla de la Parte 2).
        $this->actingAs($this->cajero)->post("/operaciones/ventas/$vtaId/anular", ['motivo' => 'x'])->assertSessionHas('error');

        $this->anularCobro($cobId);
        $this->actingAs($this->cajero)->post("/operaciones/ventas/$vtaId/anular", ['motivo' => 'Error de carga']);

        $this->assertSame('ANULADA', DB::table('ventas')->where('vta_id', $vtaId)->value('vta_estado'));
        $this->assertSame('ANULADA', $this->cuenta($credId)->cred_estado);
    }

    public function test_el_cobro_anulado_ya_no_figura_en_la_lista_de_cobranzas_ni_en_la_suma_de_finanzas(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);
        $this->anularCobro($cobId);

        $cuenta = \App\Models\CuentasCobrar::with(['detallesCobranza' => fn ($q) => $q->whereHas('cobranza', fn ($c) => $c->where('cob_estado', 'ACTIVA'))])->find($credId);
        $this->assertCount(0, $cuenta->detallesCobranza);

        $this->actingAs($this->cajero)->get('/cobranzas')->assertOk();
    }

    public function test_el_historial_de_cobros_se_ve_y_filtra(): void
    {
        [, $credId] = $this->ventaACredito();
        $a = $this->cobrar($credId, 5000);
        $b = $this->cobrar($credId, 3000);
        $this->anularCobro($a);

        // El número del cobro aparece como ">#N<" (así no se confunde con colores como #1c2434).
        $ver = fn (int $n) => ">#$n<";
        $this->actingAs($this->cajero)->get('/cobranzas/historial')->assertOk()->assertSee($ver($a), false)->assertSee($ver($b), false);
        $this->actingAs($this->cajero)->get('/cobranzas/historial?estado=ANULADA')->assertOk()->assertSee($ver($a), false)->assertDontSee($ver($b), false);
        $this->actingAs($this->cajero)->get('/cobranzas/historial?estado=ACTIVA')->assertOk()->assertSee($ver($b), false)->assertDontSee($ver($a), false);
    }

    // ------------------------------------------------------------------ auditoría y cierres

    public function test_la_pantalla_de_auditoria_exige_permiso_y_filtra(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);
        $this->anularCobro($cobId);

        $this->actingAs($this->cajero)->get('/auditoria')->assertForbidden();

        $this->actingAs($this->supervisor)->get('/auditoria')->assertOk()->assertSee('COBRO_ANULADO')->assertSee('COBRANZA');
        // Filtrada por acción: solo queda la fila de esa acción (1 fila en la tabla).
        $html = $this->actingAs($this->admin)->get('/auditoria?accion=COBRO_ANULADO')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'transition align-top'));
        $this->actingAs($this->admin)->get('/auditoria?usuario='.$this->cajero->usu_id.'&q=Monto')->assertOk()->assertSee('COBRO_ANULADO');
        $this->actingAs($this->admin)->get('/auditoria?q=zzzzz')->assertOk()->assertSee('No hay registros');
    }

    public function test_la_busqueda_de_auditoria_no_trata_porcentaje_como_comodin(): void
    {
        DB::table('auditoria')->insert(['aud_accion' => 'PRUEBA', 'aud_detalle' => 'descuento del 10 por ciento', 'aud_fecha' => now()]);

        $this->actingAs($this->admin)->get('/auditoria?q=%25')->assertOk()->assertSee('No hay registros');
    }

    public function test_el_listado_de_cierres_puede_mostrar_solo_los_que_tienen_diferencia(): void
    {
        DB::table('caja_sesiones')->insert([
            ['ses_id' => 10, 'caj_id' => 2, 'usu_id' => $this->otro->usu_id, 'ses_estado' => 'CERRADA', 'ses_fecha_cierre' => now(), 'ses_esperado_gs' => 50000, 'ses_diferencia_gs' => 0, 'ses_diferencia_usd' => 0, 'ses_diferencia_brl' => 0],
            ['ses_id' => 11, 'caj_id' => 2, 'usu_id' => $this->otro->usu_id, 'ses_estado' => 'CERRADA', 'ses_fecha_cierre' => now(), 'ses_esperado_gs' => 70000, 'ses_diferencia_gs' => -5000, 'ses_diferencia_usd' => 0, 'ses_diferencia_brl' => 0],
        ]);

        $this->actingAs($this->supervisor)->get('/finanzas/cierres')->assertOk()->assertSee('>#10<', false)->assertSee('>#11<', false);
        $this->actingAs($this->supervisor)->get('/finanzas/cierres?solo_diferencias=1')->assertOk()->assertSee('>#11<', false)->assertDontSee('>#10<', false)->assertSee('-5.000');
    }

    // ------------------------------------------------------------------ tickets

    public function test_un_cajero_solo_ve_los_tickets_de_sus_propias_ventas(): void
    {
        [$vtaId] = $this->ventaACredito();   // vendida por $this->cajero

        $this->actingAs($this->cajero)->get("/pdv/ticket-simple/$vtaId")->assertOk();
        $this->actingAs($this->otro)->get("/pdv/ticket-simple/$vtaId")->assertForbidden();
        $this->actingAs($this->otro)->get("/pdv/ticket-factura/$vtaId")->assertForbidden();

        // Supervisor (historial de ventas) y administrador sí.
        $this->actingAs($this->supervisor)->get("/pdv/ticket-simple/$vtaId")->assertForbidden(); // sin PDV_USAR no entra a la ruta
        $this->actingAs($this->admin)->get("/pdv/ticket-simple/$vtaId")->assertOk();
    }

    public function test_el_ticket_de_un_cobro_es_del_cajero_que_lo_hizo(): void
    {
        [, $credId] = $this->ventaACredito();
        $cobId = $this->cobrar($credId, 5000);

        $this->actingAs($this->cajero)->get("/cobranzas/ticket/$cobId")->assertOk()->assertSee('RECIBO DE COBRO')->assertSee('5.000');
        $this->actingAs($this->otro)->get("/cobranzas/ticket/$cobId")->assertForbidden();
        $this->actingAs($this->supervisor)->get("/cobranzas/ticket/$cobId")->assertOk();

        $this->anularCobro($cobId);
        $this->actingAs($this->cajero)->get("/cobranzas/ticket/$cobId")->assertOk()->assertSee('COBRO ANULADO');
    }

    // ------------------------------------------------------------------ zona horaria

    public function test_el_sistema_usa_la_hora_de_paraguay_por_defecto(): void
    {
        $this->assertSame('America/Asuncion', config('app.timezone'));
        $this->assertSame('America/Asuncion', config('database.connections.pgsql.timezone'));
    }

    public function test_la_conversion_de_zona_horaria_simula_convierte_y_no_se_repite(): void
    {
        DB::table('ventas')->insert(['vta_id' => 500, 'suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'vta_tipo' => 'CONTADO', 'vta_total' => 1, 'vta_estado' => 'COMPLETADA', 'vta_fecha' => '2026-10-09 15:00:00']);
        DB::table('ventas')->insert(['vta_id' => 501, 'suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'vta_tipo' => 'CONTADO', 'vta_total' => 1, 'vta_estado' => 'COMPLETADA', 'vta_fecha' => null]);

        // Simulación: no cambia nada.
        $this->artisan('tiempo:convertir')->assertSuccessful();
        $this->assertSame('2026-10-09 15:00:00', DB::table('ventas')->where('vta_id', 500)->value('vta_fecha'));

        // Real: 15:00 UTC pasa a 12:00 en Paraguay; las fechas vacías siguen vacías.
        $this->artisan('tiempo:convertir', ['--confirmar' => true])->assertSuccessful();
        $this->assertSame('2026-10-09 12:00:00', DB::table('ventas')->where('vta_id', 500)->value('vta_fecha'));
        $this->assertNull(DB::table('ventas')->where('vta_id', 501)->value('vta_fecha'));
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'CONVERSION_ZONA_HORARIA')->count());

        // Segunda vez: se niega y no vuelve a correr las horas.
        $this->artisan('tiempo:convertir', ['--confirmar' => true])->assertFailed();
        $this->assertSame('2026-10-09 12:00:00', DB::table('ventas')->where('vta_id', 500)->value('vta_fecha'));
    }

    public function test_la_conversion_no_hace_nada_si_el_sistema_ya_esta_en_la_zona_de_origen(): void
    {
        config(['app.timezone' => 'UTC']);
        DB::table('ventas')->insert(['vta_id' => 500, 'suc_id' => 1, 'cli_id' => 1, 'usu_id' => 1, 'ses_id' => 1, 'vta_tipo' => 'CONTADO', 'vta_total' => 1, 'vta_estado' => 'COMPLETADA', 'vta_fecha' => '2026-10-09 15:00:00']);

        $this->artisan('tiempo:convertir', ['--confirmar' => true])->assertSuccessful();
        $this->assertSame('2026-10-09 15:00:00', DB::table('ventas')->where('vta_id', 500)->value('vta_fecha'));
    }

    // ------------------------------------------------------------------ respaldos

    public function test_la_limpieza_de_respaldos_borra_los_viejos_pero_deja_los_ultimos_y_lo_que_no_es_suyo(): void
    {
        $dir = sys_get_temp_dir().'/resp_'.uniqid();
        mkdir($dir);

        $crear = function (string $nombre, int $diasAtras) use ($dir) {
            file_put_contents("$dir/$nombre", 'x');
            touch("$dir/$nombre", time() - $diasAtras * 86400);
        };

        $crear('dbstock_20260101_020000.dump', 100);
        $crear('dbstock_20260102_020000.dump', 90);
        $crear('dbstock_20260103_020000.dump', 80);
        $crear('dbstock_20260104_020000.dump', 70);
        $crear('dbstock_20261008_020000.dump', 1);
        $crear('notas_importantes.txt', 500);          // no es un respaldo: no se toca
        $crear('dbstock_20260105_020000.dump.partial', 500);   // tampoco

        $borrados = (new RespaldoService())->limpiarAntiguos($dir, 14, 3);

        // 5 respaldos: quedan los 3 más nuevos aunque estén viejos; se borran los 2 más antiguos.
        $this->assertCount(2, $borrados);
        $this->assertFileDoesNotExist("$dir/dbstock_20260101_020000.dump");
        $this->assertFileDoesNotExist("$dir/dbstock_20260102_020000.dump");
        $this->assertFileExists("$dir/dbstock_20260103_020000.dump");
        $this->assertFileExists("$dir/dbstock_20261008_020000.dump");
        $this->assertFileExists("$dir/notas_importantes.txt");
        $this->assertFileExists("$dir/dbstock_20260105_020000.dump.partial");

        $this->assertEqualsWithDelta(time() - 86400, (new RespaldoService())->ultimo($dir), 5);
        $this->assertNull((new RespaldoService())->ultimo($dir.'/no_existe'));

        array_map('unlink', glob("$dir/*"));
        rmdir($dir);
    }

    public function test_el_respaldo_solo_funciona_con_postgresql_y_lo_dice_con_claridad(): void
    {
        $this->artisan('respaldo:base')->expectsOutputToContain('solo está preparado para PostgreSQL')->assertFailed();
    }

    // ------------------------------------------------------------------ control de producción

    public function test_sistema_verificar_marca_como_falla_una_configuracion_insegura(): void
    {
        config(['app.debug' => true]);

        $codigo = Artisan::call('sistema:verificar');
        $salida = Artisan::output();

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('APP_DEBUG = false', $salida);
        $this->assertStringContainsString('FALLA', $salida);
        $this->assertStringContainsString('Permisos sincronizados', $salida);
    }

    public function test_sistema_verificar_detecta_permisos_nuevos_sin_sincronizar(): void
    {
        Artisan::call('sistema:verificar');
        $this->assertMatchesRegularExpression('/FALLA\s+\|\s+Permisos sincronizados\s+\|\s+Faltan: .*CAJA_OPERAR_AJENA/', Artisan::output());
    }
}
