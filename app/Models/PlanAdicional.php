<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanAdicional extends Model
{
    protected $table = 'plan_adicionales';
    protected $primaryKey = 'ada_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['ada_activo' => 'boolean'];
}
