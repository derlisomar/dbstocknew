<?php

return [

    /** Días de validez que propone el formulario (se puede elegir otro al armar cada presupuesto). */
    'validez_defecto_dias' => (int) env('PRESUPUESTO_VALIDEZ_DIAS', 7),

    /** Validez máxima permitida, en días. */
    'validez_maxima_dias' => (int) env('PRESUPUESTO_VALIDEZ_MAX', 90),

    /** Avisar "vence pronto" cuando faltan estos días o menos. */
    'aviso_dias' => 2,

    /** Zona horaria con la que se decide si un presupuesto venció (Paraguay). */
    'zona' => 'America/Asuncion',

];
