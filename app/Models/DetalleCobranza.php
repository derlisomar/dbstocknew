<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleCobranza extends Model
{
    protected $table = 'detalle_cobranzas';
    protected $primaryKey = 'det_cob_id';
    public $timestamps = false;

    protected $fillable = [
        'cob_id',
        'cred_id',
        'det_monto_pagado'
    ];

    public function cobranza()
        {
            return $this->belongsTo(Cobranza::class, 'cob_id', 'cob_id');
        }

}