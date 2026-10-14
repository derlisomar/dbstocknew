<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 20: la tabla usuarios necesita la columna remember_token. Laravel la usa para
 * "Mantenerme conectado" y la renueva al cerrar sesión; sin ella el login/logout da error 500.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('usuarios') && ! Schema::hasColumn('usuarios', 'remember_token')) {
            Schema::table('usuarios', function ($t) {
                $t->string('remember_token', 100)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usuarios') && Schema::hasColumn('usuarios', 'remember_token')) {
            Schema::table('usuarios', function ($t) {
                $t->dropColumn('remember_token');
            });
        }
    }
};
