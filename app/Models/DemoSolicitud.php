<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoSolicitud extends Model
{
    protected $table = 'demo_solicitudes';
    protected $primaryKey = 'dem_id';
    public $timestamps = false;

    protected $fillable = [
        'dem_nombre', 'dem_negocio', 'dem_email', 'dem_telefono', 'usu_id', 'dem_ip', 'dem_estado', 'dem_creada', 'dem_vence',
    ];

    protected $casts = [
        'dem_creada' => 'datetime',
        'dem_vence' => 'datetime',
    ];
}
