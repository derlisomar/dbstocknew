<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    protected $table = 'sucursales';
    protected $primaryKey = 'suc_id';
    public $timestamps = false; // Tu tabla no tiene created_at ni updated_at

    protected $fillable = [
        'suc_nombre',
        'suc_direccion',
        'suc_telefono',
        'suc_activa',
        'suc_actividad_economica',
        'suc_timbrado',
        'suc_est_punto_exp',
        'suc_timbrado_inicio',
        'suc_timbrado_fin'
     
    ];
}