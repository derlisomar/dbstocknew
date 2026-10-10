<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** php artisan test --filter=InstalarTest */
class InstalarTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablas();
    }

    private function instalar(array $extra = [])
    {
        return $this->artisan('sistema:instalar', array_merge(['--usuario' => 'dueno', '--email' => 'dueno@dobipy.com', '--nombre' => 'Dueño'], $extra))
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'ClaveLarga123')
            ->expectsQuestion('Repetila', 'ClaveLarga123');
    }

    public function test_deja_lista_una_base_nueva(): void
    {
        $this->instalar()->assertSuccessful();

        $u = User::where('usu_usuario', 'dueno')->first();
        $this->assertNotNull($u);
        $this->assertTrue($u->esAdministrador());
        $this->assertTrue(Hash::check('ClaveLarga123', $u->usu_password));
        $this->assertSame(1, DB::table('sucursales')->count());
        $this->assertSame(1, DB::table('depositos')->count());
        $this->assertSame(1, DB::table('cajas')->count());
        $this->assertGreaterThan(10, DB::table('permisos')->count());
    }

    public function test_correrlo_dos_veces_no_duplica_nada(): void
    {
        $this->instalar()->assertSuccessful();
        $this->artisan('sistema:instalar')->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame(1, DB::table('sucursales')->count());
        $this->assertSame(1, DB::table('cajas')->count());
    }

    public function test_demo_carga_productos_con_su_historial_de_stock(): void
    {
        $this->instalar(['--demo' => true])->assertSuccessful();

        $this->assertSame(14, DB::table('productos')->count());
        $this->assertSame(5, DB::table('clientes')->count());
        $this->assertSame(14, DB::table('stock_movimientos')->where('smo_tipo', 'CARGA_INICIAL')->count());
        $malos = DB::table('productos as p')->whereRaw('p.pro_stockactual <> (SELECT COALESCE(SUM(m.smo_cantidad),0) FROM stock_movimientos m WHERE m.pro_id = p.pro_id)')->count();
        $this->assertSame(0, $malos);
    }

    public function test_clave_corta_no_crea_administrador(): void
    {
        $this->artisan('sistema:instalar', ['--usuario' => 'x', '--email' => 'x@x.com', '--nombre' => 'X'])
            ->expectsQuestion('Contraseña (mínimo 10 caracteres)', 'corta')
            ->assertSuccessful();

        $this->assertSame(0, User::count());
    }
}
