<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LicenciaPago extends Model
{
    protected $table = 'licencia_pagos';
    protected $primaryKey = 'lpa_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = [
        'lpa_fecha' => 'date', 'lpa_desde' => 'date', 'lpa_hasta' => 'date', 'lpa_vence_anterior' => 'date',
        'lpa_creado' => 'datetime', 'lpa_anulado_fecha' => 'datetime',
    ];

    public function plan() { return $this->belongsTo(Plan::class, 'plan_id', 'plan_id'); }
}
