<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';
    protected $primaryKey = 'prom_id';
    public $timestamps = false;

    protected $fillable = [
        'prom_nombre', 'prom_tipo_descuento', 'prom_valor', 
        'prom_fecha_inicio', 'prom_fecha_fin', 'prom_aplica_a', 
        'cat_id', 'pro_id', 'prom_activa'
    ];

    // Relaciones
    public function producto() { return $this->belongsTo(Producto::class, 'pro_id', 'pro_id'); }
    public function categoria() { return $this->belongsTo(Categoria::class, 'cat_id', 'cat_id'); }
}