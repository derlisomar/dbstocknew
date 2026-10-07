<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cobranza extends Model
{
    protected $table = 'cobranzas';
    protected $primaryKey = 'cob_id';
    public $timestamps = false;

    protected $fillable = [
        'suc_id',
        'cli_id',
        'usu_id',
        'ses_id',
        'cob_fecha',
        'cob_monto_total',
        'cob_estado'
    ];

    public function sesion()
    {
        return $this->belongsTo(CajaSesion::class, 'ses_id', 'ses_id');
    }
}