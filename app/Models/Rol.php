<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    // Indicar explícitamente el nombre de la tabla
    protected $table = 'roles';
    
    // Clave primaria
    protected $primaryKey = 'rol_id';
    
    // Desactivar timestamps
    public $timestamps = false;

    // Relación con permisos
    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'rol_id', 'perm_id');
    }
}