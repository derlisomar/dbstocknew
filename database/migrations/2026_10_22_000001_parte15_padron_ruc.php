<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Parte 15: tabla local con el padrón público de RUC de la DNIT (solo consulta, se carga con php artisan padron:importar). */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('padron_ruc')) {
            Schema::create('padron_ruc', function (Blueprint $t) {
                $t->string('pad_ruc', 15)->primary();
                $t->string('pad_dv', 2)->nullable();
                $t->string('pad_nombre', 255);
                $t->string('pad_estado', 40)->nullable();
            });
        }
        if (DB::getDriverName() === 'pgsql') {
            // Para que buscar "10374%" use índice aunque la base no tenga collation C.
            DB::statement('CREATE INDEX IF NOT EXISTS idx_padron_ruc_prefijo ON padron_ruc (pad_ruc varchar_pattern_ops)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('padron_ruc');
    }
};
