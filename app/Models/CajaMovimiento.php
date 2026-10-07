<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaMovimiento extends Model
{
    protected $table = 'caja_movimientos';
    protected $primaryKey = 'mov_id';
    public $timestamps = false;

    protected $fillable = [
        'ses_id',
        'mov_tipo',
        'mov_monto',
        'mov_concepto',
        'mov_moneda',
        'caj_id_destino'
    ];

    public function sesion()
    {
        return $this->belongsTo(CajaSesion::class, 'ses_id', 'ses_id');
    }
}