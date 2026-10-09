<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CajaSesion extends Model
{
    protected $table = 'caja_sesiones';
    protected $primaryKey = 'ses_id';
    public $timestamps = false;

    protected $fillable = [
        'caj_id',
        'usu_id',
        'ses_fecha_apertura',
        'ses_monto_inicial_gs',
        'ses_monto_inicial_usd',
        'ses_monto_inicial_brl',
        'ses_fecha_cierre',
        'ses_monto_cierre_gs',
        'ses_monto_cierre_usd',
        'ses_monto_cierre_brl',
        'ses_estado',
        'ses_esperado_gs',
        'ses_esperado_usd',
        'ses_esperado_brl',
        'ses_diferencia_gs',
        'ses_diferencia_usd',
        'ses_diferencia_brl',
        'ses_observacion_cierre'
    ];

    public function caja()
    {
        return $this->belongsTo(Caja::class, 'caj_id', 'caj_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usu_id', 'usu_id');
    }

    public function movimientos()
    {
        return $this->hasMany(CajaMovimiento::class, 'ses_id', 'ses_id');
    }
}