<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $primaryKey = 'prov_id';
    public $timestamps = false; // La tabla no maneja created_at ni updated_at

    protected $fillable = [
        'prov_razonsocial',
        'prov_ruc',
        'prov_telefono',
        'prov_email',
        'prov_direccion'
    ];
}