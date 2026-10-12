<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PadronRuc extends Model
{
    protected $table = 'padron_ruc';

    protected $primaryKey = 'pad_ruc';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['pad_ruc', 'pad_dv', 'pad_nombre', 'pad_estado'];
}
