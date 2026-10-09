<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useTailwind();

        // En las vistas:  @modulo('compras') ... @endmodulo  (según el plan del negocio)
        Blade::if('modulo', fn (string $clave) => \App\Services\ConfiguracionService::modulo($clave));

        // Regla central de permisos: cualquier "habilidad" que se consulte
        // (->middleware('can:VENTAS_ANULAR'), @can('VENTAS_ANULAR'), $user->can(...))
        // se resuelve contra los permisos del rol del usuario.
        // Devolver null (en vez de false) deja que otras reglas futuras decidan.
        Gate::before(function ($usuario, string $habilidad) {
            if (! $usuario instanceof User) {
                return null;
            }

            return $usuario->tienePermiso($habilidad) ? true : null;
        });
    }
}
