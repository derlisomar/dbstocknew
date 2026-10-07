<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentasCobrar extends Model
{
    protected $table = 'cuentas_cobrar';
    protected $primaryKey = 'cred_id';
    public $timestamps = false;

    protected $fillable = [
        'vta_id',
        'cli_id',
        'cred_monto_total',
        'cred_saldo_pendiente',
        'cred_fecha_vencimiento',
        'cred_estado'
    ];


    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    // Relación con el historial de pagos
    public function detallesCobranza()
    {
        return $this->hasMany(DetalleCobranza::class, 'cred_id', 'cred_id');
    }
}