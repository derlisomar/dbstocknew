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

    protected $hidden = [
        'usu_password',
    ];

    /** Códigos de permiso del rol, leídos una sola vez por request. */
    private ?array $codigosPermisos = null;

    public function getAuthPassword()
    {
        return $this->usu_password;
    }

    public function rol()
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'rol_id');
    }

    /**
     * ¿El rol del usuario es uno de los "roles_admin" de config/permisos.php?
     */
    public function esAdministrador(): bool
    {
        $nombre = mb_strtolower((string) $this->rol?->rol_nombre);

        if ($nombre === '') {
            return false;
        }

        $admins = array_map('mb_strtolower', config('permisos.roles_admin', []));

        return in_array($nombre, $admins, true);
    }

    /**
     * ¿Tiene el permiso (columna perm_codigo de la tabla permisos) por medio de su rol?
     * Los administradores tienen todos.
     */
    public function tienePermiso(string $codigo): bool
    {
        if ($this->esAdministrador()) {
            return true;
        }

        if ($this->codigosPermisos === null) {
            $this->codigosPermisos = $this->rol
                ? $this->rol->permisos()->pluck('perm_codigo')->map(fn ($c) => strtoupper($c))->all()
                : [];
        }

        return in_array(strtoupper($codigo), $this->codigosPermisos, true);
    }
}
