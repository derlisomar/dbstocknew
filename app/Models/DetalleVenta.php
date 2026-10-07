<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVenta extends Model
{
    protected $table = 'detalle_ventas';
    protected $primaryKey = 'det_vta_id';
    public $timestamps = false;

    protected $fillable = [
        'vta_id',
        'pro_id',
        'det_cantidad',
        'det_preciounitario',
        'det_subtotal',
        'det_preciocosto' // <--- Añadido para el costo histórico del producto
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'pro_id', 'pro_id');
    }
}