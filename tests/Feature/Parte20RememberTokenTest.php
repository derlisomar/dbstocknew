<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** La tabla usuarios de las bases reales no traía remember_token: la migración parte20 la agrega. */
class Parte20RememberTokenTest extends TestCase
{
    public function test_login_con_recordarme_y_logout_funcionan_tras_la_migracion(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->increments('rol_id');
            $t->string('rol_nombre');
        });
        Schema::create('permisos', function (Blueprint $t) {
            $t->increments('perm_id');
            $t->string('perm_codigo');
        });
        Schema::create('rol_permisos', function (Blueprint $t) {
            $t->unsignedInteger('rol_id');
            $t->unsignedInteger('perm_id');
        });
        // usuarios SIN remember_token, como en la base real
        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('usu_id');
            $t->unsignedInteger('rol_id')->nullable();
            $t->string('usu_nombre')->nullable();
            $t->string('usu_apellido')->nullable();
            $t->string('usu_usuario')->unique();
            $t->string('usu_email')->nullable();
            $t->string('usu_password');
            $t->boolean('usu_activo')->default(true);
        });
        $rol = DB::table('roles')->insertGetId(['rol_nombre' => 'Administrador'], 'rol_id');
        $u = User::create(['rol_id' => $rol, 'usu_usuario' => 'admin', 'usu_password' => Hash::make('clave-segura-1'), 'usu_activo' => true]);

        $this->assertFalse(Schema::hasColumn('usuarios', 'remember_token'));

        $migracion = require database_path('migrations/2026_10_27_000001_parte20_usuarios_remember_token.php');
        $migracion->up();
        $migracion->up(); // idempotente

        $this->assertTrue(Schema::hasColumn('usuarios', 'remember_token'));

        $this->post('/login', ['login_input' => 'admin', 'password' => 'clave-segura-1', 'remember' => '1'])->assertRedirect();
        $this->assertAuthenticatedAs($u->fresh());
        $this->assertNotNull($u->fresh()->remember_token);

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }
}
