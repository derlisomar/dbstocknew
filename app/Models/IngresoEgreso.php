<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngresoEgreso extends Model
{
    protected $table = 'ingresos_egresos';
    protected $primaryKey = 'ie_id';
    public $timestamps = false; // Usamos nuestro campo ie_fecha

    protected $fillable = [
        'ses_id',
        'ie_tipo',
        'ie_monto',
        'ie_concepto',
        'ie_fecha'
    ];

    public function sesion()
    {
        return $this->belongsTo(CajaSesion::class, 'ses_id', 'ses_id');
    }
}