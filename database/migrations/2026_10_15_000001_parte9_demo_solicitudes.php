<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 9: registro de los pedidos de demo de la página pública.
 * Solo crea una tabla nueva. Es seguro correrla más de una vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_solicitudes')) {
            return;
        }

        Schema::create('demo_solicitudes', function (Blueprint $t) {
            $t->increments('dem_id');
            $t->string('dem_nombre', 120);
            $t->string('dem_negocio', 150);
            $t->string('dem_email', 150);
            $t->string('dem_telefono', 40)->nullable();
            $t->unsignedBigInteger('usu_id')->nullable();           // usuario demo creado
            $t->string('dem_ip', 45)->nullable();
            $t->string('dem_estado', 12)->default('ACTIVA');         // ACTIVA | VENCIDA | FALLIDA
            $t->timestamp('dem_creada')->useCurrent();
            $t->timestamp('dem_vence')->nullable();
            $t->index('dem_email');
            $t->index(['dem_estado', 'dem_vence']);
        });
    }

    public function down(): void
    {
        // A propósito vacío: no se borran solicitudes al deshacer migraciones.
    }
};
