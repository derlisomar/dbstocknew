<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovimiento extends Model
{
    protected $table = 'stock_movimientos';
    protected $primaryKey = 'smo_id';
    public $timestamps = false;

    protected $fillable = [
        'pro_id', 'smo_tipo', 'smo_cantidad', 'smo_stock_resultante',
        'smo_motivo', 'smo_referencia', 'usu_id', 'smo_fecha',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'pro_id', 'pro_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usu_id', 'usu_id');
    }
}
