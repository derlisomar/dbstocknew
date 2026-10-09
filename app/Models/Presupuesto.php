<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Presupuesto a un cliente. "Vencido" no se guarda: se calcula comparando la fecha de vencimiento con hoy,
 * así nadie tiene que correr ningún proceso para que los presupuestos venzan.
 */
class Presupuesto extends Model
{
    /** Estados en los que el presupuesto todavía puede convertirse en venta. */
    public const ABIERTOS = ['BORRADOR', 'ENVIADO', 'ACEPTADO'];

    protected $table = 'presupuestos';
    protected $primaryKey = 'pre_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'pre_fecha' => 'datetime',
        'pre_fecha_vencimiento' => 'date',
        'pre_aceptado_fecha' => 'datetime',
        'pre_facturado_fecha' => 'datetime',
    ];

    public function cliente() { return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id'); }
    public function usuario() { return $this->belongsTo(User::class, 'usu_id', 'usu_id'); }
    public function venta() { return $this->belongsTo(Venta::class, 'vta_id', 'vta_id'); }
    public function detalles() { return $this->hasMany(DetallePresupuesto::class, 'pre_id', 'pre_id'); }

    /** Hoy en la zona horaria del negocio, como texto Y-m-d. */
    public static function hoy(): string
    {
        return now(config('presupuestos.zona', 'America/Asuncion'))->toDateString();
    }

    // ------------------------------------------------------------------ listados

    /** Abiertos y dentro de su plazo de validez. */
    public function scopeVigentes(Builder $q): Builder
    {
        return $q->whereIn('pre_estado', self::ABIERTOS)->where('pre_fecha_vencimiento', '>=', self::hoy());
    }

    /** Abiertos cuyo plazo de validez ya pasó. */
    public function scopeVencidos(Builder $q): Builder
    {
        return $q->whereIn('pre_estado', self::ABIERTOS)->where('pre_fecha_vencimiento', '<', self::hoy());
    }

    /** Aceptados por el cliente, todavía dentro del plazo y sin convertir en venta. */
    public function scopePorVender(Builder $q): Builder
    {
        return $q->where('pre_estado', 'ACEPTADO')->where('pre_fecha_vencimiento', '>=', self::hoy());
    }

    // ------------------------------------------------------------------ estado

    public function estaAbierto(): bool
    {
        return in_array($this->pre_estado, self::ABIERTOS, true);
    }

    public function estaVencido(): bool
    {
        return $this->estaAbierto() && $this->pre_fecha_vencimiento->toDateString() < self::hoy();
    }

    /** Estado que se muestra: igual al guardado, salvo que haya vencido. */
    public function estadoVisible(): string
    {
        return $this->estaVencido() ? 'VENCIDO' : $this->pre_estado;
    }

    /** Días que faltan para el vencimiento (negativo si ya venció). */
    public function diasRestantes(): int
    {
        return (int) \Carbon\Carbon::parse(self::hoy())->diffInDays($this->pre_fecha_vencimiento->copy()->startOfDay(), false);
    }

    public function getNumeroAttribute(): string
    {
        return 'PRE-'.str_pad((string) $this->pre_id, 5, '0', STR_PAD_LEFT);
    }

    public function getNombreClienteAttribute(): string
    {
        if ($this->cliente) {
            return trim($this->cliente->cli_nombre.' '.($this->cliente->cli_apellido ?? ''));
        }

        return $this->pre_cliente_nombre ?: 'Cliente sin registrar';
    }
}
