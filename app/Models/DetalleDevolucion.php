<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleDevolucion extends Model
{
    protected $table = 'detalle_devoluciones';
    protected $primaryKey = 'ddv_id';
    public $timestamps = false;

    protected $fillable = ['dev_id', 'det_vta_id', 'pro_id', 'ddv_cantidad', 'ddv_preciounitario', 'ddv_subtotal'];
}
