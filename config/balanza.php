<?php

/*
|--------------------------------------------------------------------------
| Etiquetas de balanza (supermercados)
|--------------------------------------------------------------------------
| La balanza imprime una etiqueta con un código de barras EAN-13 que lleva adentro el producto y el peso
| (o el importe). Al escanearla en el punto de venta, el sistema carga el producto y la cantidad solo.
|
| Estructura del código (13 dígitos):   [prefijo][PLU][valor][dígito verificador]
|   - prefijo : con qué dígitos empiezan las etiquetas (normalmente 2)
|   - PLU     : código del producto en la balanza (se carga en la ficha del producto, campo «Código de balanza»)
|   - valor   : peso en GRAMOS (modo PESO) o importe en guaraníes (modo PRECIO)
| Los dígitos del valor son los que sobran: 12 - largo del prefijo - dígitos del PLU.
| Configuralo igual que la balanza (en el manual figura como «formato de etiqueta» o «código de barras»).
*/

return [
    'activa' => (bool) env('BALANZA_ACTIVA', true),
    'prefijo' => (string) env('BALANZA_PREFIJO', '2'),
    'digitos_plu' => (int) env('BALANZA_DIGITOS_PLU', 6),
    // PESO: el valor son gramos (o mililitros). PRECIO: el valor es el importe en Gs.
    'valor' => strtoupper((string) env('BALANZA_VALOR', 'PESO')),
];
