<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles con acceso total
    |--------------------------------------------------------------------------
    | Los usuarios cuyo rol tenga uno de estos nombres (sin distinguir
    | mayúsculas) pasan todos los controles de permisos. Así el administrador
    | nunca puede quedar bloqueado por un permiso mal asignado.
    |
    | Se cambia en el archivo .env, separando con comas:
    |   PERMISOS_ROLES_ADMIN="Administrador,Admin"
    */
    'roles_admin' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PERMISOS_ROLES_ADMIN', 'Administrador'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Catálogo de permisos
    |--------------------------------------------------------------------------
    | Código => descripción y módulo. Se cargan en la tabla "permisos" con:
    |   php artisan permisos:sincronizar
    | y después se asignan a cada rol desde "Gestión de Roles".
    */
    'catalogo' => [
        'USUARIOS_GESTIONAR'  => ['modulo' => 'USUARIOS',      'descripcion' => 'Crear, editar y desactivar usuarios; administrar roles y permisos'],
        'CONFIG_GESTIONAR'    => ['modulo' => 'CONFIGURACION', 'descripcion' => 'Sucursales, cajas, depósitos, cotización y terminal'],
        'CATALOGO_GESTIONAR'  => ['modulo' => 'CATALOGO',      'descripcion' => 'Productos, categorías, proveedores y promociones'],
        'CLIENTES_GESTIONAR'  => ['modulo' => 'CLIENTES',      'descripcion' => 'Alta, edición y baja de clientes'],
        'CLIENTES_CREDITO'    => ['modulo' => 'CLIENTES',      'descripcion' => 'Habilitar crédito, fijar límite, marcar mayorista y bloquear clientes'],
        'PDV_USAR'            => ['modulo' => 'VENTAS',        'descripcion' => 'Vender en el punto de venta'],
        'VENTAS_HISTORIAL'    => ['modulo' => 'VENTAS',        'descripcion' => 'Ver y exportar el historial de ventas'],
        'VENTAS_ANULAR'       => ['modulo' => 'VENTAS',        'descripcion' => 'Anular ventas'],
        'VENTAS_DEVOLVER'     => ['modulo' => 'VENTAS',        'descripcion' => 'Procesar devoluciones de ventas'],
        'COBRANZAS_ANULAR'    => ['modulo' => 'COBRANZAS',     'descripcion' => 'Anular cobros ya registrados (devuelve la deuda al cliente)'],
        'COBRANZAS_REGISTRAR' => ['modulo' => 'COBRANZAS',     'descripcion' => 'Cobrar cuentas a crédito'],
        'CAJA_ABRIR_CERRAR'   => ['modulo' => 'FINANZAS',      'descripcion' => 'Abrir y cerrar caja'],
        'CAJA_TRANSFERIR'     => ['modulo' => 'FINANZAS',      'descripcion' => 'Transferir dinero entre cajas'],
        'CAJA_INGRESO_EGRESO' => ['modulo' => 'FINANZAS',      'descripcion' => 'Registrar ingresos y egresos extra de caja'],
        'FINANZAS_VER'        => ['modulo' => 'FINANZAS',      'descripcion' => 'Ver movimientos, cierres e ingresos/egresos'],
        'CAJA_OPERAR_AJENA'   => ['modulo' => 'FINANZAS',      'descripcion' => 'Cerrar, transferir y registrar movimientos en cajas abiertas por otra persona (responsable)'],
        'AUDITORIA_VER'       => ['modulo' => 'AUDITORIA',     'descripcion' => 'Ver el registro de auditoría (quién hizo qué)'],
        'STOCK_AJUSTAR'       => ['modulo' => 'CATALOGO',      'descripcion' => 'Ajustar stock, ingresar mercadería y hacer conteos (queda en el historial)'],
        'REPORTES_VER'        => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes (rentabilidad / curva ABC)'],
    ],

];
