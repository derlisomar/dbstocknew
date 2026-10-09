<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCompra extends Model
{
    protected $table = 'detalle_compras';
    protected $primaryKey = 'dco_id';
    public $timestamps = false;
    protected $guarded = [];

    public function producto() { return $this->belongsTo(Producto::class, 'pro_id', 'pro_id'); }
}
