<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parte 13: las solicitudes de demo guardan el motivo cuando el correo no se pudo enviar
 * (así el vendedor ve el problema en su panel y no se pierde ningún posible cliente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demo_solicitudes') && ! Schema::hasColumn('demo_solicitudes', 'dem_detalle')) {
            Schema::table('demo_solicitudes', function (Blueprint $t) {
                $t->text('dem_detalle')->nullable();
            });
        }
    }

    public function down(): void
    {
        // A propósito vacío.
    }
};
