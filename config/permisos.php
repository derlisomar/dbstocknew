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
        'PADRON_GESTIONAR'    => ['modulo' => 'CLIENTES',      'descripcion' => 'Importar, editar y eliminar registros del padrón de RUC (la consulta al cargar clientes no lo necesita)'],
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
        'COMPRAS_REGISTRAR'   => ['modulo' => 'COMPRAS',       'descripcion' => 'Registrar compras a proveedores y ver el listado de compras'],
        'COMPRAS_ANULAR'      => ['modulo' => 'COMPRAS',       'descripcion' => 'Anular compras y devolver mercadería al proveedor'],
        'PAGOS_PROVEEDORES'   => ['modulo' => 'COMPRAS',       'descripcion' => 'Ver cuentas a pagar y registrar o anular pagos a proveedores'],
        'PRESUPUESTOS_GESTIONAR' => ['modulo' => 'VENTAS',     'descripcion' => 'Crear, editar, aceptar, rechazar y renovar presupuestos (quien vende en caja puede verlos y convertirlos en venta)'],
        'CONTABILIDAD_VER'    => ['modulo' => 'CONTABILIDAD',  'descripcion' => 'Ver libro diario, mayor, balances y libro IVA'],
        'CONTABILIDAD_GESTIONAR' => ['modulo' => 'CONTABILIDAD', 'descripcion' => 'Plan de cuentas, mapeos, asientos manuales de ajuste y cierre de períodos'],
        'REPORTES_VER'        => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes (rentabilidad / curva ABC)'],
        'REPORTES_VENTAS'     => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes de ventas (por período, cajero, forma de pago, categoría, horas pico, anulaciones)'],
        'REPORTES_STOCK'      => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes de stock (valorizado, reposición, vencimientos, kardex, ajustes)'],
        'REPORTES_CARTERA'    => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes de clientes y cartera (cuentas por cobrar, cobranzas, mejores clientes, límites de crédito)'],
        'REPORTES_COMPRAS'    => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes de compras y proveedores (compras, cuentas por pagar, pagos, evolución de costos)'],
        'REPORTES_CAJA'       => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes de caja y finanzas (cierres, faltantes y sobrantes, flujo de caja)'],
        'REPORTES_FISCAL'     => ['modulo' => 'REPORTES',      'descripcion' => 'Ver reportes fiscales (libros de IVA, resumen de IVA, facturas por timbrado, numeración, vigencia de timbrados)'],
        'REPORTES_COSTOS'     => ['modulo' => 'REPORTES',      'descripcion' => 'Ver costos, utilidades y márgenes dentro de los reportes'],
        'REPORTES_EXPORTAR'   => ['modulo' => 'REPORTES',      'descripcion' => 'Descargar reportes en Excel y PDF'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles listos para usar
    |--------------------------------------------------------------------------
    | Se crean con:  php artisan roles:preparar   (también lo hace php artisan migrate y sistema:instalar).
    | Solo se crean los que faltan: un rol que ya existe con ese nombre no se toca.
    | Después se pueden ajustar permiso por permiso desde "Gestión de Roles".
    | El Administrador no está acá: tiene acceso total por su nombre (ver 'roles_admin').
    |
    |   'permisos'     => lista exacta de códigos del catálogo
    |   'todos_menos'  => todos los del catálogo salvo estos
    */
    'roles_base' => [
        'Gerente' => [
            'descripcion' => 'Supervisa todo el negocio: ventas, caja, compras, reportes y auditoría. No administra usuarios ni configuración.',
            'todos_menos' => ['USUARIOS_GESTIONAR', 'CONFIG_GESTIONAR', 'CONTABILIDAD_GESTIONAR'],
        ],
        'Cajero' => [
            'descripcion' => 'Vende y cobra en el punto de venta, abre y cierra su caja y cobra cuentas a crédito.',
            'permisos' => ['PDV_USAR', 'CAJA_ABRIR_CERRAR', 'CAJA_INGRESO_EGRESO', 'COBRANZAS_REGISTRAR', 'CLIENTES_GESTIONAR', 'PRESUPUESTOS_GESTIONAR', 'VENTAS_HISTORIAL'],
        ],
        'Vendedor' => [
            'descripcion' => 'Atiende clientes y arma presupuestos. No maneja dinero: el cajero los convierte en venta y cobra.',
            'permisos' => ['PRESUPUESTOS_GESTIONAR', 'CLIENTES_GESTIONAR'],
        ],
        'Encargado de stock' => [
            'descripcion' => 'Carga productos, recibe mercadería, registra compras y ajusta el stock.',
            'permisos' => ['CATALOGO_GESTIONAR', 'STOCK_AJUSTAR', 'COMPRAS_REGISTRAR', 'REPORTES_STOCK', 'REPORTES_COMPRAS', 'REPORTES_EXPORTAR'],
        ],
        'Contador' => [
            'descripcion' => 'Contabilidad, libro IVA y reportes. Ve las ventas y la caja, pero no vende ni cobra.',
            'permisos' => ['CONTABILIDAD_VER', 'CONTABILIDAD_GESTIONAR', 'REPORTES_VER', 'REPORTES_VENTAS', 'REPORTES_STOCK', 'REPORTES_CARTERA', 'REPORTES_COMPRAS', 'REPORTES_CAJA', 'REPORTES_FISCAL', 'REPORTES_COSTOS', 'REPORTES_EXPORTAR', 'FINANZAS_VER', 'PAGOS_PROVEEDORES', 'VENTAS_HISTORIAL', 'AUDITORIA_VER'],
        ],
    ],

];
