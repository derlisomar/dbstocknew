<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deposito extends Model
{
    protected $table = 'depositos';
    protected $primaryKey = 'dep_id';
    public $timestamps = false;

    protected $fillable = [
        'suc_id',
        'dep_nombre',
        'dep_descripcion',
        'dep_activo'
    ];

    public function sucursal() {
        return $this->belongsTo(Sucursal::class, 'suc_id', 'suc_id');
    }
}