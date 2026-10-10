<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlanAdicional;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Lo que se muestra en la sección de precios de la página pública. */
class PlanesPublicos
{
    /** @return Collection<int,Plan> */
    public static function planes(): Collection
    {
        if (! Schema::hasTable('planes') || ! Schema::hasColumn('planes', 'plan_publico')) {
            return collect();
        }

        return Plan::where('plan_publico', true)->where('plan_activo', true)->orderBy('plan_orden')->orderBy('plan_id')->get();
    }

    /** Solo los adicionales que tienen precio cargado. @return Collection<int,PlanAdicional> */
    public static function adicionales(): Collection
    {
        if (! Schema::hasTable('plan_adicionales')) {
            return collect();
        }

        return PlanAdicional::where('ada_activo', true)->where('ada_precio', '>', 0)->orderBy('ada_orden')->orderBy('ada_id')->get();
    }

    public static function gs(float|int|string $n): string
    {
        return 'Gs. '.number_format((float) $n, 0, ',', '.');
    }

    public static function limite(int $n): string
    {
        return $n > 0 ? (string) $n : 'Ilimitados';
    }
}
