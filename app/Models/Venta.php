<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';
    protected $primaryKey = 'vta_id';
    public $timestamps = false;

    protected $fillable = [
        'suc_id',
        'cli_id',
        'usu_id',
        'ses_id',
        'vta_tipo',
        'vta_formapago',
        'vta_total',
        'vta_estado',

        // Campos fiscales y de pago que se perdían:
        'vta_timbrado',
        'vta_nro_factura',
        'vta_total_exenta',
        'vta_total_iva5',
        'vta_total_iva10',
        'vta_formapago',
        'nro_transferencia',
        'vta_moneda',
        'vta_anulada_por',
        'vta_anulada_fecha',
        'vta_motivo_anulacion'

    ];

    public function detalles()
    {
        return $this->hasMany(DetalleVenta::class, 'vta_id', 'vta_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cli_id', 'cli_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usu_id', 'usu_id');
    }

    // 👇 AGREGA ESTA RELACIÓN PARA SOLUCIONAR EL ERROR AL ANULAR 👇
    public function sesion()
    {
        return $this->belongsTo(CajaSesion::class, 'ses_id', 'ses_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'suc_id', 'suc_id');
    }
}