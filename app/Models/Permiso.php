<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';
    protected $primaryKey = 'perm_id';
    public $timestamps = false;

    protected $fillable = [
        'perm_codigo',
        'perm_descripcion',
        'perm_modulo'
    ];

    // Relación inversa: Un permiso puede pertenecer a muchos roles
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_permisos', 'perm_id', 'rol_id');
    }
}