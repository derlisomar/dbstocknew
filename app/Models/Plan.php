<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'planes';
    protected $primaryKey = 'plan_id';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['plan_activo' => 'boolean', 'plan_publico' => 'boolean', 'plan_destacado' => 'boolean'];

    /** @return list<string> */
    public function modulosExtra(): array
    {
        $lista = json_decode((string) $this->plan_modulos, true);

        return is_array($lista) ? array_values(array_filter($lista, 'is_string')) : [];
    }

    /**
     * Líneas de la lista de características. Las que empiezan con "-" se muestran tachadas (no incluidas).
     *
     * @return list<array{texto:string,incluye:bool}>
     */
    public function caracteristicas(): array
    {
        $out = [];
        foreach (preg_split('/\R/u', (string) $this->plan_caracteristicas) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $no = str_starts_with($linea, '-');
            $texto = trim($no ? substr($linea, 1) : $linea);
            if ($texto !== '') {
                $out[] = ['texto' => $texto, 'incluye' => ! $no];
            }
        }

        return $out;
    }

    /** "/mes", "/año", "pago único" o "cada 3 meses". */
    public function periodoTexto(): string
    {
        return match ((int) $this->plan_meses) {
            0 => 'pago único',
            1 => '/mes',
            12 => '/año',
            default => 'cada '.(int) $this->plan_meses.' meses',
        };
    }
}
