<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cotizacion extends Model
{
    protected $table = 'cotizaciones';
    protected $primaryKey = 'cot_id';
    public $timestamps = false;

    protected $fillable = [
        'cot_fecha',
        'cot_dolar',
        'cot_real',
        'cot_activa'
    ];
}