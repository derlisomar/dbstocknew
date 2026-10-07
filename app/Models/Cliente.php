<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';
    protected $primaryKey = 'cli_id';
    public $timestamps = false;

    protected $fillable = [
        'cli_nombre',
        'cli_apellido',
        'cli_ruc_ci',
        'cli_telefono',
        'cli_direccion',
        'cli_email',
        'cli_limite_credito',
        'cli_es_mayorista',
        'cli_permitir_credito',
        'cli_bloqueado'
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cli_id', 'cli_id')->where('vta_estado', '!=', 'ANULADA');
    }

    public function cuentasCobrar()
    {
        return $this->hasMany(CuentasCobrar::class, 'cli_id', 'cli_id')->where('cred_estado', 'PENDIENTE');
    }
}