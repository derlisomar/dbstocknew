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
        'cob_estado',
        'cob_formapago',
        'cob_anulada_por',
        'cob_anulada_fecha',
        'cob_motivo_anulacion',
    ];

    public function detalles()
    {
        return $this->hasMany(DetalleCobranza::class, 'cob_id', 'cob_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usu_id', 'usu_id');
    }

    public function sesion()
    {
        return $this->belongsTo(CajaSesion::class, 'ses_id', 'ses_id');
    }
}