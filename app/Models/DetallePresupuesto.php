<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetallePresupuesto extends Model
{
    protected $table = 'detalle_presupuestos';
    protected $primaryKey = 'dpr_id';
    public $timestamps = false;
    protected $guarded = [];

    public function producto() { return $this->belongsTo(Producto::class, 'pro_id', 'pro_id'); }
}
