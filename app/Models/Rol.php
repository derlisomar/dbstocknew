<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'rol_id';
    public $timestamps = false; // Asumiendo que tu tabla no usa created_at y updated_at

    // 👇 ESTE ES EL BLOQUE QUE SOLUCIONA EL ERROR 👇
    protected $fillable = [
        'rol_nombre',
        'rol_descripcion'
    ];

    // Tu relación con los permisos
    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'rol_id', 'perm_id');
    }
}