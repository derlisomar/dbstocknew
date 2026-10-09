<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';
    protected $primaryKey = 'aud_id';
    public $timestamps = false;

    protected $fillable = ['usu_id', 'aud_accion', 'aud_tabla', 'aud_registro_id', 'aud_detalle', 'aud_ip', 'aud_fecha'];
}
