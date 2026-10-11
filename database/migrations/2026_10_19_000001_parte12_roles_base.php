<?php

use App\Services\RolesBase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 12: roles listos para usar (Gerente, Cajero, Vendedor, Encargado de stock y Contador).
 * Solo crea los que faltan; no toca roles que ya existan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && Schema::hasTable('permisos') && Schema::hasTable('rol_permisos')) {
            RolesBase::preparar(false);
        }
    }

    public function down(): void
    {
        // A propósito vacío: no se borran roles al deshacer migraciones.
    }
};
