<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PadronImportador;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Padrón de RUC de la DNIT: importador y búsqueda. Ejecutar: php artisan test --filter=Parte15PadronTest */
class Parte15PadronTest extends TestCase
{
    use \Tests\Concerns\EsquemaTest;

    protected User $cajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->crearTablas();
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Cajero'], 'rol_id');
        $permId = DB::table('permisos')->insertGetId(['perm_codigo' => 'PDV_USAR'], 'perm_id');
        DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        $this->cajero = User::create(['rol_id' => $rolId, 'usu_nombre' => 'Ana', 'usu_usuario' => 'caja1', 'usu_email' => 'c@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
    }

    private function archivo(string $contenido, string $ext = 'txt'): string
    {
        $r = tempnam(sys_get_temp_dir(), 'pad').'.'.$ext;
        file_put_contents($r, $contenido);

        return $r;
    }

    public function test_lee_lineas_con_distintos_separadores_y_codificacion(): void
    {
        $a = PadronImportador::leerLinea("80053637|A Y O S.A|1|80053637-1|ACTIVO\r\n");
        $this->assertSame(['pad_ruc' => '80053637', 'pad_dv' => '1', 'pad_nombre' => 'A Y O S.A', 'pad_estado' => 'ACTIVO'], $a);
        $this->assertSame('PEÑA JUAN', PadronImportador::leerLinea(mb_convert_encoding('1234567;PEÑA JUAN;3;;', 'ISO-8859-1', 'UTF-8'))['pad_nombre']);
        $this->assertNull(PadronImportador::leerLinea('RUC|RAZON SOCIAL|DV'));
        $this->assertNull(PadronImportador::leerLinea(''));
    }

    public function test_importa_txt_y_zip_sin_duplicar(): void
    {
        $txt = $this->archivo("1037415|PEREZ JUAN|9||ACTIVO\n2966368|58XCAR|7||ACTIVO\n1037415|PEREZ JUAN CARLOS|9||ACTIVO\nbasura\n");
        [$n, $ign] = PadronImportador::importar($txt);
        $this->assertSame(3, $n);
        $this->assertSame(1, $ign);
        $this->assertSame(2, DB::table('padron_ruc')->count());
        $this->assertSame('PEREZ JUAN CARLOS', DB::table('padron_ruc')->where('pad_ruc', '1037415')->value('pad_nombre'));

        $zip = tempnam(sys_get_temp_dir(), 'pz').'.zip';
        $z = new \ZipArchive;
        $z->open($zip, \ZipArchive::CREATE);
        $z->addFromString('ruc1.txt', "80013743|AGRO SANTA ROSA S.A|4||ACTIVO\n");
        $z->close();
        PadronImportador::importar($zip);
        $this->assertSame(3, DB::table('padron_ruc')->count());
    }

    public function test_busqueda_por_ruc_y_cliente_existente(): void
    {
        PadronImportador::importar($this->archivo("1037415|PEREZ, JUAN|9||ACTIVO\n80013743|AGRO SANTA ROSA S.A|4||ACTIVO\n"));
        DB::table('clientes')->insert(['cli_id' => 1, 'cli_nombre' => 'Agro', 'cli_ruc_ci' => '80013743-4', 'cli_es_mayorista' => 0, 'cli_permitir_credito' => 0, 'cli_bloqueado' => 0, 'cli_limite_credito' => 0]);

        $r = $this->actingAs($this->cajero)->getJson('/padron/ruc?q=1037415')->assertOk();
        $this->assertSame('1037415-9', $r->json('padron.0.ruc'));
        $this->assertSame('JUAN', $r->json('padron.0.nombre'));
        $this->assertSame('PEREZ', $r->json('padron.0.apellido'));
        $this->assertNull($r->json('existente'));

        $r = $this->actingAs($this->cajero)->getJson('/padron/ruc?q=80013743-4')->assertOk();
        $this->assertSame(1, $r->json('existente.cli_id'));

        $this->actingAs($this->cajero)->getJson('/padron/ruc?q=abc')->assertOk()->assertJson(['padron' => []]);
    }

    public function test_requiere_permiso(): void
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Nada'], 'rol_id');
        $u = User::create(['rol_id' => $rolId, 'usu_nombre' => 'X', 'usu_usuario' => 'x', 'usu_email' => 'x@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
        $this->actingAs($u)->getJson('/padron/ruc?q=1037415')->assertStatus(403);
    }

    public function test_comando_probar_no_guarda(): void
    {
        $this->artisan('padron:importar', ['archivos' => [$this->archivo("1037415|PEREZ JUAN|9||ACTIVO\n")], '--probar' => true])->assertSuccessful();
        $this->assertSame(0, DB::table('padron_ruc')->count());
    }

    public function test_importa_desde_la_pantalla_de_clientes(): void
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Gestor'], 'rol_id');
        $permId = DB::table('permisos')->insertGetId(['perm_codigo' => 'CLIENTES_GESTIONAR'], 'perm_id');
        DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);
        $u = User::create(['rol_id' => $rolId, 'usu_nombre' => 'G', 'usu_usuario' => 'g', 'usu_email' => 'g@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);

        $f = \Illuminate\Http\UploadedFile::fake()->createWithContent('ruc0.txt', "1037415|PEREZ, JUAN|9||ACTIVO\n2966368|58XCAR|7||CANCELADO\n");
        $this->actingAs($u)->post('/clientes/padron/importar', ['archivos' => [$f]])->assertRedirect()->assertSessionHas('padron_ok');
        $this->assertSame(2, DB::table('padron_ruc')->count());

        $this->actingAs($u)->post('/clientes/padron/importar', [])->assertSessionHasErrors('archivos');
        $this->actingAs($u)->followingRedirects()->from('/clientes')->post('/clientes/padron/importar', [])->assertSee('Elegí al menos un archivo');
        $mal = \Illuminate\Http\UploadedFile::fake()->create('virus.exe', 5);
        $this->actingAs($u)->post('/clientes/padron/importar', ['archivos' => [$mal]])->assertSessionHasErrors();

        // sin permiso de clientes no puede importar
        $this->actingAs($this->cajero)->post('/clientes/padron/importar', ['archivos' => [$f]])->assertStatus(403);
    }

    private function gestor(): User
    {
        $rolId = DB::table('roles')->insertGetId(['rol_nombre' => 'Gestor2'], 'rol_id');
        $permId = DB::table('permisos')->insertGetId(['perm_codigo' => 'CLIENTES_GESTIONAR'], 'perm_id');
        DB::table('rol_permisos')->insert(['rol_id' => $rolId, 'perm_id' => $permId]);

        return User::create(['rol_id' => $rolId, 'usu_nombre' => 'G2', 'usu_usuario' => 'g2', 'usu_email' => 'g2@x.com', 'usu_password' => Hash::make('x'), 'usu_activo' => true]);
    }

    public function test_listado_filtra_pagina_y_permite_crud(): void
    {
        $u = $this->gestor();
        for ($i = 1; $i <= 30; $i++) {
            DB::table('padron_ruc')->insert(['pad_ruc' => (string) (1000000 + $i), 'pad_dv' => (string) ($i % 10), 'pad_nombre' => "PERSONA $i", 'pad_estado' => $i <= 20 ? 'ACTIVO' : 'CANCELADO']);
        }
        DB::table('padron_ruc')->insert(['pad_ruc' => '555', 'pad_dv' => '1', 'pad_nombre' => 'GARCIA, MARIA', 'pad_estado' => 'SUSPENSION TEMPORAL']);

        $this->actingAs($u)->get('/padron-ruc')->assertOk()->assertSee('31 registros')->assertSee('PERSONA 1');
        $this->actingAs($u)->get('/padron-ruc?page=2')->assertOk()->assertSee('PERSONA 30');
        $this->actingAs($u)->get('/padron-ruc?estado=CANCELADO')->assertOk()->assertSee('10 registros');
        $this->actingAs($u)->get('/padron-ruc?estado=SUSPENSION+TEMPORAL')->assertOk()->assertSee('GARCIA, MARIA');
        $this->actingAs($u)->get('/padron-ruc?q=garcia+maria')->assertOk()->assertSee('1 registros');
        $this->actingAs($u)->get('/padron-ruc?q=100000')->assertOk()->assertSee('9 registros');

        $this->actingAs($u)->post('/padron-ruc', ['pad_ruc' => '777', 'pad_dv' => '2', 'pad_nombre' => ' lopez  ana ', 'pad_estado' => 'ACTIVO'])->assertSessionHas('padron_ok');
        $this->assertSame('LOPEZ ANA', DB::table('padron_ruc')->where('pad_ruc', '777')->value('pad_nombre'));
        $this->actingAs($u)->post('/padron-ruc', ['pad_ruc' => '777', 'pad_nombre' => 'X'])->assertSessionHasErrors('pad_ruc');
        $this->actingAs($u)->post('/padron-ruc', ['pad_ruc' => 'abc', 'pad_nombre' => 'X'])->assertSessionHasErrors('pad_ruc');

        $this->actingAs($u)->put('/padron-ruc/777', ['pad_dv' => '3', 'pad_nombre' => 'Lopez Ana Maria', 'pad_estado' => 'CANCELADO'])->assertSessionHas('padron_ok');
        $this->assertSame('CANCELADO', DB::table('padron_ruc')->where('pad_ruc', '777')->value('pad_estado'));
        $this->actingAs($u)->delete('/padron-ruc/777')->assertSessionHas('padron_ok');
        $this->assertSame(0, DB::table('padron_ruc')->where('pad_ruc', '777')->count());

        $this->actingAs($this->cajero)->get('/padron-ruc')->assertStatus(403);
    }
}
