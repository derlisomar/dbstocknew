<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuentaPagar extends Model
{
    protected $table = 'cuentas_pagar';
    protected $primaryKey = 'cpa_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['cpa_fecha_vencimiento' => 'date'];

    public function compra() { return $this->belongsTo(Compra::class, 'com_id', 'com_id'); }
    public function proveedor() { return $this->belongsTo(Proveedor::class, 'prov_id', 'prov_id'); }
    public function pagos() { return $this->hasMany(PagoProveedor::class, 'cpa_id', 'cpa_id'); }
}
