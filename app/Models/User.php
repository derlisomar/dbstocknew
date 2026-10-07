<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'usu_id';
    public $timestamps = false;

    protected $fillable = [
        'rol_id',
        'usu_cedula',
        'usu_nombre',
        'usu_apellido',
        'usu_usuario',
        'usu_email',
        'usu_password',
        'usu_activo',
    ];

    public function getAuthPassword()
    {
        return $this->usu_password;
    }

    protected $hidden = [
        'usu_password',
    ];

    // 👇 AGREGA ESTA RELACIÓN PARA SOLUCIONAR EL ERROR 👇
    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'rol_id');
    }
}