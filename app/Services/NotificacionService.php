<?php

namespace App\Services;

use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avisos de la campanita. Se calculan al momento con los datos reales (no hay tabla de notificaciones)
 * y cada usuario ve solo los que corresponden a lo que su rol puede hacer.
 */
class NotificacionService
{
    /** @return array<int, array{nivel:string,titulo:string,detalle:string,url:?string}> */
    public static function para(User $u): array
    {
        $items = [];
        $hoy = now()->startOfDay();
        $gs = fn ($n) => 'Gs. '.number_format((float) $n, 0, ',', '.');

        // Licencia / plan
        if ($u->tienePermiso('CONFIG_GESTIONAR')) {
            $lic = LicenciaService::estado();
            if (! empty($lic['mensaje'])) {
                $items[] = ['nivel' => $lic['bloquea'] ? 'critico' : 'aviso', 'titulo' => 'Tu plan', 'detalle' => $lic['mensaje'], 'url' => null];
            }
        }

        // Stock bajo
        if ($u->tienePermiso('CATALOGO_GESTIONAR') || $u->tienePermiso('STOCK_AJUSTAR')) {
            $n = Producto::where('pro_activo', true)->whereColumn('pro_stockactual', '<=', 'pro_stockminimo')->count();
            if ($n > 0) {
                $items[] = ['nivel' => 'aviso', 'titulo' => $n === 1 ? '1 producto con stock bajo' : "{$n} productos con stock bajo", 'detalle' => 'Llegaron al stock mínimo: conviene reponerlos.', 'url' => route('inventario.index')];
            }
        }

        // Deuda vencida de clientes
        if ($u->tienePermiso('CLIENTES_CREDITO') && Schema::hasTable('cuentas_cobrar')) {
            $q = DB::table('cuentas_cobrar')->where('cred_estado', 'PENDIENTE')->where('cred_fecha_vencimiento', '<', $hoy);
            $n = (clone $q)->count();
            if ($n > 0) {
                $items[] = ['nivel' => 'critico', 'titulo' => $n === 1 ? '1 cuenta de cliente vencida' : "{$n} cuentas de clientes vencidas", 'detalle' => 'Te deben '.$gs((clone $q)->sum('cred_saldo_pendiente')).' pasada la fecha.', 'url' => route('cobranzas.index')];
            }
        }

        // Pagos a proveedores vencidos
        if ($u->tienePermiso('PAGOS_PROVEEDORES') && Schema::hasTable('cuentas_pagar')) {
            $q = DB::table('cuentas_pagar')->where('cpa_estado', 'PENDIENTE')->whereNotNull('cpa_fecha_vencimiento')->where('cpa_fecha_vencimiento', '<', $hoy->toDateString());
            $n = (clone $q)->count();
            if ($n > 0) {
                $items[] = ['nivel' => 'critico', 'titulo' => $n === 1 ? '1 pago a proveedor vencido' : "{$n} pagos a proveedores vencidos", 'detalle' => 'Debés '.$gs((clone $q)->sum('cpa_saldo_pendiente')).' pasada la fecha.', 'url' => route('cuentas_pagar.index')];
            }
        }

        // Presupuestos abiertos y vencidos
        if ($u->tienePermiso('PRESUPUESTOS_GESTIONAR') && Schema::hasTable('presupuestos') && Schema::hasColumn('presupuestos', 'pre_fecha_vencimiento')) {
            $n = DB::table('presupuestos')->whereIn('pre_estado', Presupuesto::ABIERTOS)->where('pre_fecha_vencimiento', '<', $hoy->toDateString())->count();
            if ($n > 0) {
                $items[] = ['nivel' => 'aviso', 'titulo' => $n === 1 ? '1 presupuesto vencido' : "{$n} presupuestos vencidos", 'detalle' => 'Siguen abiertos: cerralos o renovalos.', 'url' => route('presupuestos.index')];
            }
        }

        // Pedidos de demo (solo el dueño de la instalación con la página pública activa)
        if (config('landing.activa') && $u->esAdministrador() && Schema::hasTable('demo_solicitudes')) {
            $n = DB::table('demo_solicitudes')->where('dem_creada', '>=', now()->subDay())->count();
            if ($n > 0) {
                $items[] = ['nivel' => 'info', 'titulo' => $n === 1 ? '1 pedido de demo nuevo' : "{$n} pedidos de demo nuevos", 'detalle' => 'En las últimas 24 horas. Te llegó el aviso por correo.', 'url' => null];
            }
        }

        return $items;
    }
}
