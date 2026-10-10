<?php

/*
|--------------------------------------------------------------------------
| Página pública (landing) y demo
|--------------------------------------------------------------------------
| Todo se puede cambiar desde el .env sin tocar código.
*/
return [

    // Nombre que aparece en la página y en el pie.
    'empresa' => env('LANDING_EMPRESA', 'Dobi Soluciones Informáticas'),

    // false = instalación de un cliente: no hay página pública, la raíz lleva directo al sistema y /demo no existe.
    'activa' => (bool) env('LANDING_ACTIVA', true),

    // Dos dominios (opcional): la página pública en uno y el sistema en otro. Dejar vacíos para usar uno solo.
    'dominio_web' => env('LANDING_DOMINIO', ''),        // ej. dbstock.com.py
    'dominio_app' => env('LANDING_DOMINIO_APP', ''),    // ej. app.dbstock.com.py

    // Contacto opcional. Si están vacíos, el botón no se muestra.
    'whatsapp' => preg_replace('/\D+/', '', (string) env('LANDING_WHATSAPP', '')),   // ej. 595981123456
    'correo_contacto' => env('LANDING_CORREO', ''),

    // Demo: se puede apagar con LANDING_DEMO=false (el formulario muestra un aviso).
    'demo_activa' => (bool) env('LANDING_DEMO', true),
    'dias_demo' => (int) env('LANDING_DEMO_DIAS', 7),
    'max_demos_activas' => (int) env('LANDING_DEMO_MAX', 100),
    'rol_demo' => 'Demo',

    // Permisos del rol "Demo". Se asignan solo cuando el rol se crea; después se ajustan en Gestión de Roles.
    // Quedan afuera a propósito: usuarios, configuración, auditoría, transferencias entre cajas y anulaciones.
    'permisos_demo' => [
        'PDV_USAR', 'VENTAS_HISTORIAL', 'CLIENTES_GESTIONAR', 'CLIENTES_CREDITO', 'CATALOGO_GESTIONAR',
        'COBRANZAS_REGISTRAR', 'CAJA_ABRIR_CERRAR', 'CAJA_INGRESO_EGRESO', 'FINANZAS_VER', 'STOCK_AJUSTAR',
        'COMPRAS_REGISTRAR', 'PAGOS_PROVEEDORES', 'PRESUPUESTOS_GESTIONAR', 'REPORTES_VER',
    ],
];
