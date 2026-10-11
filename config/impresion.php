<?php

/*
|--------------------------------------------------------------------------
| Impresora matricial (Epson LX-350 y similares)
|--------------------------------------------------------------------------
| La hoja sale en texto de 80 columnas (10 caracteres por pulgada) sobre papel continuo.
| Medidas del papel en milímetros. Se pueden cambiar en el archivo .env.
|   Papel continuo de 9,5 pulgadas de ancho = 241 mm.
|   Largo del formulario: media carta = 140 mm, carta = 279 mm.
| El papel también hay que definirlo en el driver de la impresora (mismo ancho y largo).
*/
return [
    'matricial' => [
        'ancho_mm' => (int) env('IMPRESION_MATRICIAL_ANCHO_MM', 241),
        'alto_mm' => (int) env('IMPRESION_MATRICIAL_ALTO_MM', 140),
        'margen_izq_mm' => (int) env('IMPRESION_MATRICIAL_MARGEN_IZQ_MM', 15),
        'margen_sup_mm' => (int) env('IMPRESION_MATRICIAL_MARGEN_SUP_MM', 6),
    ],
];
