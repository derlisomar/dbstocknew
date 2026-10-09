<?php

/*
|--------------------------------------------------------------------------
| Módulos del sistema y ediciones
|--------------------------------------------------------------------------
| El panel del vendedor activa o desactiva estos módulos por negocio.
|   'basica' => true  : viene activado en la edición BÁSICA (la reducida).
| La edición COMPLETA los trae todos. Cada módulo se puede prender o apagar
| por separado para un cliente, sin importar la edición.
|
| Siempre incluido en ambas ediciones (no se puede apagar): punto de venta,
| clientes, productos, cajas y finanzas, historial de ventas, usuarios y roles.
*/
return [

    'ediciones' => [
        'COMPLETA' => 'Completa',
        'BASICA' => 'Básica',
    ],

    'nucleo' => [
        'Punto de venta (contado y crédito)',
        'Clientes, productos y categorías',
        'Cajas, apertura/cierre e ingresos y egresos',
        'Historial de ventas, anulaciones y devoluciones',
        'Usuarios, roles y permisos',
        'Sucursales y configuración general',
    ],

    'catalogo' => [
        'cobranzas' => [
            'nombre' => 'Cobranzas y créditos',
            'descripcion' => 'Cobrar las ventas a crédito, cuentas por cobrar y control de clientes. Sin este módulo el punto de venta no ofrece venta a crédito.',
            'basica' => true,
        ],
        'presupuestos' => [
            'nombre' => 'Presupuestos',
            'descripcion' => 'Presupuestos con fecha de validez y conversión en venta.',
            'basica' => true,
        ],
        'inventario' => [
            'nombre' => 'Inventario y ajustes de stock',
            'descripcion' => 'Historial de movimientos, ajustes e ingreso manual de mercadería.',
            'basica' => true,
        ],
        'compras' => [
            'nombre' => 'Compras, proveedores y cuentas a pagar',
            'descripcion' => 'Registrar compras, costo real, deuda y pagos a proveedores.',
            'basica' => false,
        ],
        'promociones' => [
            'nombre' => 'Promociones',
            'descripcion' => 'Descuentos por producto o categoría con fechas.',
            'basica' => false,
        ],
        'reportes_avanzados' => [
            'nombre' => 'Reportes avanzados',
            'descripcion' => 'Rentabilidad por producto y curva ABC.',
            'basica' => false,
        ],
        'depositos' => [
            'nombre' => 'Depósitos',
            'descripcion' => 'Administración de depósitos por sucursal.',
            'basica' => false,
        ],
        'auditoria' => [
            'nombre' => 'Registro de auditoría',
            'descripcion' => 'Pantalla para consultar quién hizo qué y cuándo.',
            'basica' => false,
        ],
        'multimoneda' => [
            'nombre' => 'Ventas en dólares y reales',
            'descripcion' => 'Permite cobrar en USD o BRL además de guaraníes, con cotización.',
            'basica' => false,
        ],
    ],

];
