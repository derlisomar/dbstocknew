<?php

namespace Tests\Feature;

use App\Models\User;
use App\Reportes\Catalogo;
use App\Reportes\Exportador;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Centro de reportes: pantalla, Excel, PDF, permisos y desglose por sucursal. php artisan test --filter=Parte16ReportesTest */
class Parte16ReportesTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    private function usuario(string $nombre, array $permisos, string $rol = 'Analista'): User
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => $rol.$nombre], 'rol_id');
        foreach ($permisos as $c) {
            $pid = DB::table('permisos')->where('perm_codigo', $c)->value('perm_id') ?: DB::table('permisos')->insertGetId(['perm_codigo' => $c], 'perm_id');
            DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $pid]);
        }

        return User::create(['rol_id' => $rolId, 'usu_nombre' => $nombre, 'usu_apellido' => 'Test', 'usu_usuario' => strtolower($nombre), 'usu_email' => $nombre.'@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();

        // columnas / tablas que el esquema mínimo de tests no trae
        foreach ([['cuentas_cobrar', 'cred_fecha_emision', 'dateTime'], ['cobranzas', 'cob_formapago', 'string'], ['ventas', 'vta_anulada_por', 'integer'], ['ventas', 'vta_anulada_fecha', 'dateTime'], ['ventas', 'vta_motivo_anulacion', 'string'], ['proveedores', 'prov_telefono', 'string']] as [$t, $c, $tipo]) {
            if (! Schema::hasColumn($t, $c)) {
                Schema::table($t, fn (Blueprint $b) => $b->{$tipo}($c)->nullable());
            }
        }
        if (! Schema::hasTable('devoluciones')) {
            Schema::create('devoluciones', fn (Blueprint $t) => [$t->increments('dev_id'), $t->unsignedInteger('vta_id'), $t->unsignedInteger('usu_id')->nullable(), $t->dateTime('dev_fecha'), $t->decimal('dev_total', 18, 2), $t->string('dev_motivo')->nullable()]);
        }
        if (! Schema::hasTable('stock_movimientos')) {
            Schema::create('stock_movimientos', function (Blueprint $t) {
                $t->increments('smo_id'); $t->unsignedInteger('pro_id'); $t->string('smo_tipo'); $t->decimal('smo_cantidad', 18, 3); $t->decimal('smo_stock_resultante', 18, 3);
                $t->string('smo_motivo')->nullable(); $t->string('smo_referencia')->nullable(); $t->unsignedInteger('usu_id')->nullable(); $t->dateTime('smo_fecha');
            });
        }
        if (! Schema::hasColumn('productos', 'pro_unidad')) {
            Schema::table('productos', fn (Blueprint $b) => $b->string('pro_unidad')->default('UN'));
        }
        if (! Schema::hasTable('auditoria')) {
            Schema::create('auditoria', function (Blueprint $t) {
                $t->increments('aud_id'); $t->unsignedInteger('usu_id')->nullable(); $t->string('aud_accion'); $t->string('aud_tabla')->nullable();
                $t->string('aud_registro_id')->nullable(); $t->text('aud_detalle')->nullable(); $t->string('aud_ip')->nullable(); $t->dateTime('aud_fecha')->nullable();
            });
        }

        $this->completo = $this->usuario('Full', ['REPORTES_VENTAS', 'REPORTES_STOCK', 'REPORTES_CARTERA', 'REPORTES_COMPRAS', 'REPORTES_CAJA', 'REPORTES_FISCAL', 'REPORTES_COSTOS', 'REPORTES_EXPORTAR']);
        $this->sembrar();
    }

    protected User $completo;

    private function sembrar(): void
    {
        DB::table('sucursales')->insert([['suc_id' => 1, 'suc_nombre' => 'Casa Central', 'suc_activa' => 1], ['suc_id' => 2, 'suc_nombre' => 'Sucursal Este', 'suc_activa' => 1]]);
        DB::table('categorias')->insert([['cat_id' => 1, 'cat_nombre' => 'Bebidas'], ['cat_id' => 2, 'cat_nombre' => 'Limpieza']]);
        DB::table('depositos')->insert([['dep_id' => 1, 'suc_id' => 1, 'dep_nombre' => 'Depósito 1'], ['dep_id' => 2, 'suc_id' => 2, 'dep_nombre' => 'Depósito Este']]);
        DB::table('proveedores')->insert(['prov_id' => 1, 'prov_razonsocial' => 'Distribuidora SA']);
        $manana = now()->addDays(10)->format('Y-m-d');
        DB::table('productos')->insert([
            ['pro_id' => 1, 'cat_id' => 1, 'pro_nombre' => 'Gaseosa 2L', 'pro_codigo' => 'G2', 'pro_precioventa' => 10000, 'pro_preciocosto' => 6000, 'pro_stockactual' => 3, 'pro_stockminimo' => 10, 'suc_id' => 1, 'dep_id' => 1, 'prov_id' => 1, 'pro_fechavencimiento' => $manana],
            ['pro_id' => 2, 'cat_id' => 2, 'pro_nombre' => 'Lavandina', 'pro_codigo' => 'LV', 'pro_precioventa' => 5000, 'pro_preciocosto' => 3000, 'pro_stockactual' => 50, 'pro_stockminimo' => 5, 'suc_id' => 2, 'dep_id' => 2, 'prov_id' => null, 'pro_fechavencimiento' => null],
        ]);
        DB::table('clientes')->insert([
            ['cli_id' => 1, 'cli_nombre' => 'Mostrador', 'cli_apellido' => null, 'cli_ruc_ci' => '44444401-7', 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_es_mayorista' => 0, 'cli_limite_credito' => 0],
            ['cli_id' => 2, 'cli_nombre' => 'Pedro', 'cli_apellido' => 'Gómez', 'cli_ruc_ci' => '1234567-8', 'cli_permitir_credito' => 1, 'cli_bloqueado' => 0, 'cli_es_mayorista' => 0, 'cli_limite_credito' => 100000],
        ]);
        $hoy = now()->format('Y-m-d');
        $mk = fn ($id, $suc, $cli, $tipo, $fp, $total, $estado = 'CONFIRMADA') => ['vta_id' => $id, 'suc_id' => $suc, 'cli_id' => $cli, 'usu_id' => $this->completo->usu_id, 'ses_id' => 1, 'vta_fecha' => $hoy.' 10:15:00',
            'vta_tipo' => $tipo, 'vta_formapago' => $fp, 'vta_total' => $total, 'vta_estado' => $estado, 'vta_total_iva10' => $total, 'vta_total_exenta' => 0, 'vta_total_iva5' => 0];
        DB::table('ventas')->insert([
            $mk(1, 1, 1, 'CONTADO', 'EFECTIVO', 20000), $mk(2, 1, 2, 'CREDITO', 'CREDITO', 30000), $mk(3, 2, 1, 'CONTADO', 'TARJETA', 5000), $mk(4, 2, 1, 'CONTADO', 'EFECTIVO', 99000, 'ANULADA'),
        ]);
        DB::table('detalle_ventas')->insert([
            ['vta_id' => 1, 'pro_id' => 1, 'det_cantidad' => 2, 'det_preciounitario' => 10000, 'det_subtotal' => 20000, 'det_preciocosto' => 6000],
            ['vta_id' => 2, 'pro_id' => 1, 'det_cantidad' => 3, 'det_preciounitario' => 10000, 'det_subtotal' => 30000, 'det_preciocosto' => 6000],
            ['vta_id' => 3, 'pro_id' => 2, 'det_cantidad' => 1, 'det_preciounitario' => 5000, 'det_subtotal' => 5000, 'det_preciocosto' => 3000],
        ]);
        DB::table('cuentas_cobrar')->insert(['cred_id' => 1, 'vta_id' => 2, 'cli_id' => 2, 'cred_monto_total' => 30000, 'cred_saldo_pendiente' => 30000, 'cred_fecha_emision' => $hoy.' 10:15:00', 'cred_fecha_vencimiento' => now()->subDays(40)->format('Y-m-d'), 'cred_estado' => 'PENDIENTE']);
        DB::table('cobranzas')->insert(['cob_id' => 1, 'suc_id' => 1, 'cli_id' => 2, 'usu_id' => $this->completo->usu_id, 'ses_id' => 1, 'cob_fecha' => $hoy.' 11:00:00', 'cob_monto_total' => 10000, 'cob_estado' => 'ACTIVA', 'cob_formapago' => 'EFECTIVO']);
        DB::table('devoluciones')->insert(['vta_id' => 1, 'usu_id' => $this->completo->usu_id, 'dev_fecha' => $hoy.' 12:00:00', 'dev_total' => 10000, 'dev_motivo' => 'Falla']);
        DB::table('compras')->insert([
            ['com_id' => 1, 'prov_id' => 1, 'usu_id' => $this->completo->usu_id, 'suc_id' => 1, 'com_fecha' => $hoy.' 08:00:00', 'com_nro_documento' => '001-001-0000001', 'com_tipo' => 'CREDITO', 'com_total' => 100000, 'com_estado' => 'REGISTRADA'],
            ['com_id' => 2, 'prov_id' => 1, 'usu_id' => $this->completo->usu_id, 'suc_id' => 2, 'com_fecha' => $hoy.' 08:30:00', 'com_nro_documento' => '001-001-0000002', 'com_tipo' => 'CONTADO', 'com_total' => 40000, 'com_estado' => 'REGISTRADA'],
        ]);
        DB::table('detalle_compras')->insert([
            ['com_id' => 1, 'pro_id' => 1, 'dco_cantidad' => 20, 'dco_costo' => 5000, 'dco_subtotal' => 100000, 'dco_devuelta' => 0],
            ['com_id' => 2, 'pro_id' => 2, 'dco_cantidad' => 10, 'dco_costo' => 4000, 'dco_subtotal' => 40000, 'dco_devuelta' => 0],
        ]);
        DB::table('cuentas_pagar')->insert(['cpa_id' => 1, 'com_id' => 1, 'prov_id' => 1, 'cpa_monto_total' => 100000, 'cpa_saldo_pendiente' => 100000, 'cpa_fecha_vencimiento' => now()->subDays(5)->format('Y-m-d'), 'cpa_estado' => 'PENDIENTE']);
        DB::table('cajas')->insert([['caj_id' => 1, 'suc_id' => 1, 'caj_nombre' => 'Caja 1'], ['caj_id' => 2, 'suc_id' => 2, 'caj_nombre' => 'Caja Este']]);
        DB::table('caja_sesiones')->insert(['ses_id' => 1, 'caj_id' => 1, 'usu_id' => $this->completo->usu_id, 'ses_estado' => 'CERRADA', 'ses_fecha_apertura' => $hoy.' 07:00:00', 'ses_fecha_cierre' => $hoy.' 19:00:00',
            'ses_monto_inicial_gs' => 100000, 'ses_esperado_gs' => 500000, 'ses_monto_cierre_gs' => 490000, 'ses_diferencia_gs' => -10000]);
        DB::table('caja_movimientos')->insert([
            ['ses_id' => 1, 'mov_tipo' => 'INGRESO', 'mov_monto' => 50000, 'mov_concepto' => 'Venta', 'mov_fecha' => $hoy.' 10:00:00', 'mov_moneda' => 'GS', 'mov_forma_pago' => 'EFECTIVO'],
            ['ses_id' => 1, 'mov_tipo' => 'EGRESO', 'mov_monto' => 20000, 'mov_concepto' => 'Pago', 'mov_fecha' => $hoy.' 11:00:00', 'mov_moneda' => 'GS', 'mov_forma_pago' => 'EFECTIVO'],
        ]);
        DB::table('ingresos_egresos')->insert(['ses_id' => 1, 'ie_tipo' => 'EGRESO', 'ie_monto' => 20000, 'ie_concepto' => 'Limpieza', 'ie_fecha' => $hoy.' 11:00:00']);
        DB::table('stock_movimientos')->insert(['pro_id' => 1, 'smo_tipo' => 'AJUSTE_SALIDA', 'smo_cantidad' => -1, 'smo_stock_resultante' => 3, 'smo_motivo' => 'Rotura', 'usu_id' => $this->completo->usu_id, 'smo_fecha' => $hoy.' 09:00:00']);
    }

    public function test_todos_los_reportes_abren_y_se_exportan(): void
    {
        foreach (array_keys(Catalogo::todos()) as $clave) {
            $this->actingAs($this->completo)->get("/reportes/$clave")->assertOk();

            $x = $this->actingAs($this->completo)->get("/reportes/$clave/xlsx")->assertOk();
            $this->assertStringStartsWith('PK', $x->getContent(), "xlsx $clave");

            $p = $this->actingAs($this->completo)->get("/reportes/$clave/pdf")->assertOk();
            $this->assertStringStartsWith('%PDF', $p->getContent(), "pdf $clave");
        }
    }

    public function test_ventas_por_periodo_suma_solo_confirmadas_y_separa_por_sucursal(): void
    {
        $f = ['desde' => now()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d'), 'agrupar' => 'dia'];
        $todo = Catalogo::ejecutar('ventas_periodo', $f, null);
        $this->assertSame(55000, (int) $todo['filas'][0][2]);   // 20000 + 30000 + 5000, sin la anulada
        $this->assertSame(1, (int) $todo['filas'][0][7]);       // 1 anulada
        $this->assertSame(50000, (int) Catalogo::ejecutar('ventas_periodo', $f, 1)['filas'][0][2]);
        $this->assertSame(5000, (int) Catalogo::ejecutar('ventas_periodo', $f, 2)['filas'][0][2]);

        $r = $this->actingAs($this->completo)->get('/reportes/ventas_periodo?desde='.$f['desde'].'&hasta='.$f['hasta'])->assertOk();
        $r->assertSee('Consolidado (todas las sucursales)')->assertSee('Sucursal: Casa Central')->assertSee('Sucursal: Sucursal Este');
    }

    public function test_excel_trae_una_hoja_por_sucursal_y_pdf_pagina(): void
    {
        $x = $this->actingAs($this->completo)->get('/reportes/ventas_usuario')->assertOk();
        $doc = ['negocio' => 'N', 'titulo' => 'T', 'subtitulo' => 'S', 'generado' => 'g', 'usuario' => 'u', 'secciones' => [
            ['titulo' => 'Consolidado', 'columnas' => [['t' => 'A', 'tipo' => 'texto']], 'filas' => [['x']]], ['titulo' => 'Sucursal: Este', 'columnas' => [['t' => 'A', 'tipo' => 'texto']], 'filas' => []],
        ]];
        $tmp = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($tmp, Exportador::xlsx($doc));
        $z = new \ZipArchive;
        $z->open($tmp);
        $wb = $z->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('name="Consolidado"', $wb);
        $this->assertStringContainsString('name="Sucursal Este"', $wb);   // los ":" no se permiten en nombres de hoja
        $this->assertSame(1, preg_match('/^%PDF-1\.4/', Exportador::pdf($doc)));
    }

    public function test_costos_se_ocultan_sin_permiso_de_costos(): void
    {
        $sinCostos = $this->usuario('Sin', ['REPORTES_VENTAS']);
        $this->actingAs($sinCostos)->get('/reportes/ventas_categoria')->assertOk()->assertDontSee('Utilidad Gs.')->assertSee('Ingreso Gs.');
        $this->actingAs($this->completo)->get('/reportes/ventas_categoria')->assertOk()->assertSee('Utilidad Gs.');
    }

    public function test_permisos_por_area_y_exportar(): void
    {
        $soloStock = $this->usuario('Stock', ['REPORTES_STOCK']);
        $this->actingAs($soloStock)->get('/reportes')->assertOk()->assertSee('Stock valorizado')->assertDontSee('Ventas por período');
        $this->actingAs($soloStock)->get('/reportes/ventas_periodo')->assertForbidden();
        $this->actingAs($soloStock)->get('/reportes/stock_valorizado')->assertOk();
        $this->actingAs($soloStock)->get('/reportes/stock_valorizado/xlsx')->assertForbidden();   // sin REPORTES_EXPORTAR

        $this->actingAs($this->usuario('Nada', ['VENTAS_HISTORIAL']))->get('/reportes')->assertForbidden();
    }

    public function test_cartera_antiguedad_y_stock(): void
    {
        $f = ['desde' => now()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')];
        $a = Catalogo::ejecutar('cartera_antiguedad', $f, null);
        $this->assertSame('Pedro Gómez', $a['filas'][0][0]);
        $this->assertSame(30000, (int) $a['filas'][0][5]);   // 40 días de atraso: tramo 31-60
        $this->assertSame(30000, (int) $a['filas'][0][8]);

        $bajo = Catalogo::ejecutar('stock_bajo_minimo', $f, 1);
        $this->assertSame('Gaseosa 2L', $bajo['filas'][0][1]);
        $this->assertSame(17.0, (float) $bajo['filas'][0][7]);   // 2*10 - 3

        $val = Catalogo::ejecutar('stock_valorizado', ['agrupar' => 'categoria'] + $f, null);
        $this->assertSame(2, count($val['filas']));
        $this->assertCount(7, $val['columnas']);
        $this->assertCount(4, Catalogo::sinCostos($val)['columnas']);
    }

    public function test_compras_y_caja(): void
    {
        $f = ['desde' => now()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d'), 'agrupar' => 'dia'];
        $this->assertSame(140000, (int) Catalogo::ejecutar('compras_periodo', $f, null)['filas'][0][2]);
        $this->assertSame(100000, (int) Catalogo::ejecutar('compras_periodo', $f, 1)['filas'][0][2]);
        $a = Catalogo::ejecutar('pagar_antiguedad', $f, null);
        $this->assertSame(100000, (int) $a['filas'][0][3 + 1]);   // 5 días de atraso: tramo 1-30
        $d = Catalogo::ejecutar('caja_diferencias', $f, 1);
        $this->assertSame('FALTANTE', $d['filas'][0][9]);
        $this->assertSame(0, count(Catalogo::ejecutar('caja_diferencias', $f, 2)['filas']));
        $fl = Catalogo::ejecutar('caja_flujo', $f, null);
        $this->assertSame([50000, 20000, 30000], array_map('intval', array_slice($fl['filas'][0], 1, 3)));
        $this->assertSame(1, count(Catalogo::ejecutar('compras_costos', $f, null)['filas']) - 1);
    }

    public function test_fiscal_libros_resumen_y_numeracion(): void
    {
        $f = ['desde' => now()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')];
        DB::table('ventas')->where('vta_id', 1)->update(['vta_timbrado' => 'T1', 'vta_nro_factura' => '0000001']);
        DB::table('ventas')->where('vta_id', 2)->update(['vta_timbrado' => 'T1', 'vta_nro_factura' => '0000004']);
        DB::table('ventas')->where('vta_id', 4)->update(['vta_timbrado' => 'T2', 'vta_nro_factura' => '0000001']);
        DB::table('sucursales')->where('suc_id', 1)->update(['suc_timbrado' => 'T1', 'suc_timbrado_fin' => now()->addDays(10)->format('Y-m-d'), 'suc_est_punto_exp' => '001-001', 'suc_factura_secuencia' => 5]);

        $l = Catalogo::ejecutar('fiscal_libro_ventas', $f, 1);
        $this->assertCount(2, $l['filas']);
        $this->assertSame([18182, 1818, 0, 0, 0, 20000], array_map('intval', array_slice($l['filas'][0], 6)));   // 20.000 al 10 %: base + IVA
        $todo = Catalogo::ejecutar('fiscal_libro_ventas', $f, null);
        $anulada = array_values(array_filter($todo['filas'], fn ($r) => $r[1] === 'Anulada'))[0];
        $this->assertSame(0, (int) $anulada[11]);

        $r = Catalogo::ejecutar('fiscal_resumen_iva', $f, null);
        $this->assertSame(1, count($r['filas']));
        $this->assertSame(5000, (int) $r['filas'][0][4]);    // débito fiscal: IVA de 55.000
        $this->assertSame(12727, (int) $r['filas'][0][5]);   // crédito fiscal estimado: IVA de 140.000
        $this->assertSame(5000 - 12727, (int) $r['filas'][0][6]);

        $h = Catalogo::ejecutar('fiscal_huecos', $f, 1);
        $this->assertSame(['0000002', '0000003', 2], [$h['filas'][0][3], $h['filas'][0][4], $h['filas'][0][5]]);
        $t = Catalogo::ejecutar('fiscal_timbrados', $f, 1);
        $this->assertSame(2, (int) $t['filas'][0][5]);
        $this->assertSame(2, (int) $t['filas'][0][7]);   // faltan 2 números
        $v = Catalogo::ejecutar('fiscal_vigencia', $f, 1);
        $this->assertSame('POR VENCER', $v['filas'][0][7]);
        $this->assertSame('0000005', $v['filas'][0][6]);
        $ruc = Catalogo::ejecutar('fiscal_ventas_ruc', $f, null);
        $this->assertSame(55000, (int) array_sum(array_column($ruc['filas'], 7)));
    }

    public function test_tablero_y_reportes_por_usuario(): void
    {
        $r = $this->actingAs($this->completo)->get('/reportes')->assertOk();
        $r->assertSee('Ventas de hoy')->assertSee('Stock crítico')->assertSee('Deuda vencida de clientes')->assertSee('Diferencia de caja del mes')->assertSee('Timbrados');
        $r->assertSee('Gs. 55.000');   // ventas confirmadas de hoy

        $solo = $this->usuario('SoloStock', ['REPORTES_STOCK']);
        $this->actingAs($solo)->get('/reportes')->assertOk()->assertSee('Stock crítico')->assertDontSee('Ventas de hoy')->assertDontSee('Deuda vencida');

        $f = ['desde' => now()->format('Y-m-d'), 'hasta' => now()->format('Y-m-d')];
        $rend = Catalogo::ejecutar('usuario_rendimiento', $f, null);
        $fila = $rend['filas'][0];
        $this->assertSame(55000, (int) $fila[2]);   // total vendido por el único vendedor
        $this->assertSame(3, (int) $fila[1]);
        $this->assertSame(1, (int) $fila[5]);       // 1 anulada
        $this->assertSame(10000, (int) $fila[8]);   // cobranza
        $this->assertSame(-10000, (int) $fila[9]);  // diferencia de caja

        $this->assertCount(1, Catalogo::ejecutar('usuario_dia', $f + ['usu_id' => $this->completo->usu_id], null)['filas']);
        $this->assertCount(0, Catalogo::ejecutar('usuario_dia', $f + ['usu_id' => 9999], null)['filas']);
        $this->actingAs($this->completo)->get('/reportes/usuario_dia?usu_id='.$this->completo->usu_id)->assertOk()->assertSee('Usuario:');
    }

    public function test_control_de_productos_ya_no_existe_y_sus_datos_estan_en_reportes(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('productos.control'));
        $this->actingAs($this->completo)->get('/operaciones/productos-control')->assertNotFound();
        // lo que mostraba ahora vive en el Centro de Reportes
        foreach (['stock_bajo_minimo', 'ventas_producto', 'stock_rotacion'] as $clave) {
            $this->assertArrayHasKey($clave, Catalogo::todos());
        }
    }

    public function test_exportar_deja_rastro_en_auditoria(): void
    {
        $this->actingAs($this->completo)->get('/reportes/ventas_listado/pdf')->assertOk();
        $this->assertSame(1, DB::table('auditoria')->where('aud_accion', 'REPORTE_EXPORTADO')->count());
    }
}
