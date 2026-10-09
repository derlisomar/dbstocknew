<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoProveedor extends Model
{
    protected $table = 'pagos_proveedores';
    protected $primaryKey = 'pag_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['pag_fecha' => 'datetime'];

    public function cuenta() { return $this->belongsTo(CuentaPagar::class, 'cpa_id', 'cpa_id'); }
    public function proveedor() { return $this->belongsTo(Proveedor::class, 'prov_id', 'prov_id'); }
    public function usuario() { return $this->belongsTo(User::class, 'usu_id', 'usu_id'); }
}
