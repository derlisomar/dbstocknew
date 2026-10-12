<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un código de barras de un producto (un producto puede tener varios). */
class ProductoCodigo extends Model
{
    protected $table = 'producto_codigos';
    protected $primaryKey = 'cod_id';
    public $timestamps = false;

    protected $fillable = ['pro_id', 'cod_codigo'];
}
