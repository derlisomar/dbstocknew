<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cómo se calcula el costo de un producto al comprarlo
    |--------------------------------------------------------------------------
    |   promedio : costo promedio ponderado (recomendado). Mezcla lo que ya tenés en
    |              stock con lo que acabás de comprar, así el margen refleja la realidad.
    |   ultimo   : el producto pasa a costar lo que pagaste en la última compra.
    |
    | Se cambia en el archivo .env:  COMPRAS_COSTO=ultimo
    */
    'costo' => env('COMPRAS_COSTO', 'promedio'),

    /** Días de plazo que propone el formulario para compras a crédito. */
    'plazo_defecto_dias' => (int) env('COMPRAS_PLAZO_DIAS', 30),

];
