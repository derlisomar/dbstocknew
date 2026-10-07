<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Caja extends Model
{
    protected $table = 'cajas';
    protected $primaryKey = 'caj_id';
    public $timestamps = false;
    
    protected $fillable = ['suc_id', 'caj_nombre', 'caj_activa', 'caj_saldo_gs', 'caj_saldo_usd', 'caj_saldo_brl', 'caj_impresora','caj_tipo_impresion'];

    public function sucursal() {
        return $this->belongsTo(Sucursal::class, 'suc_id', 'suc_id');
    }


    
}

