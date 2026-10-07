<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';
    protected $primaryKey = 'pro_id';
    public $timestamps = false;

protected $fillable = [
        'cat_id', 'prov_id', 'suc_id', 'dep_id', 'pro_codigo', 'pro_nombre', 
        'pro_descripcion', 'pro_preciocosto', 'pro_precioventa', 'pro_preciomayorista', 
        'pro_stockactual', 'pro_stockminimo', 'pro_fechavencimiento', 'pro_imagen', 'pro_activo','pro_permite_mayorista',
        'pro_en_promocion',
        'pro_precio_promocional',
        'pro_tipo_iva'
    ];

// Relación para calcular las ventas de este producto
    public function detalle_ventas()
    {
        return $this->hasMany(DetalleVenta::class, 'pro_id', 'pro_id');
    }

    public function categoria() { return $this->belongsTo(Categoria::class, 'cat_id', 'cat_id'); }
    public function proveedor() { return $this->belongsTo(Proveedor::class, 'prov_id', 'prov_id'); }
    public function sucursal() { return $this->belongsTo(Sucursal::class, 'suc_id', 'suc_id'); }
    public function deposito() { return $this->belongsTo(Deposito::class, 'dep_id', 'dep_id'); }
}

