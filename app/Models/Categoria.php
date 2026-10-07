<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table = 'categorias';
    protected $primaryKey = 'cat_id';
    public $timestamps = false;

    protected $fillable = [
        'cat_nombre',
        'cat_descripcion'
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class, 'cat_id', 'cat_id');
    }
}