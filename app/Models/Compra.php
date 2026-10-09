<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';
    protected $primaryKey = 'com_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['com_fecha' => 'datetime'];

    public function proveedor() { return $this->belongsTo(Proveedor::class, 'prov_id', 'prov_id'); }
    public function usuario() { return $this->belongsTo(User::class, 'usu_id', 'usu_id'); }
    public function detalles() { return $this->hasMany(DetalleCompra::class, 'com_id', 'com_id'); }
    public function cuenta() { return $this->hasOne(CuentaPagar::class, 'com_id', 'com_id'); }
}
