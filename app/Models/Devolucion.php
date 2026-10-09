<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devolucion extends Model
{
    protected $table = 'devoluciones';
    protected $primaryKey = 'dev_id';
    public $timestamps = false;

    protected $fillable = ['vta_id', 'usu_id', 'ses_id', 'dev_fecha', 'dev_total', 'dev_motivo'];

    public function detalles()
    {
        return $this->hasMany(DetalleDevolucion::class, 'dev_id', 'dev_id');
    }
}
