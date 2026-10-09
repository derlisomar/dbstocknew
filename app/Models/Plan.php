<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';
    protected $primaryKey = 'plan_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['plan_activo' => 'boolean'];

    /** @return list<string> */
    public function modulosExtra(): array
    {
        $lista = json_decode((string) $this->plan_modulos, true);

        return is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
    }
}
