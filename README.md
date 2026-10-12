# dbstock

Sistema web de ventas, stock, caja, cobranzas, compras, presupuestos y contabilidad, hecho con **Laravel 12** y **PostgreSQL**.
Incluye punto de venta (ticket y matricial), roles y permisos, licencias por plan, página pública con pedido de demo y un panel privado para el vendedor del sistema.

Desarrollado por Dobi Soluciones Informáticas.

## Requisitos
- PHP 8.2 o superior (extensiones `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`)
- PostgreSQL 12 o superior
- Composer (y Node.js solo si vas a recompilar los estilos con `npm run build`)

## Instalación en una máquina nueva
```bash
composer install
cp .env.example .env
php artisan key:generate
```
1. En `.env` revisá `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`.
2. Creá la base y cargá la estructura:
```bash
createdb -U postgres dbStockNew
psql -U postgres -d dbStockNew -f database/schema/dbstock_estructura.sql
php artisan migrate          # carga los planes y adicionales; no repite el resto
php artisan sistema:instalar # crea permisos, roles, administrador, sucursal, depósito y caja
php artisan sistema:verificar
php artisan serve
```
Agregá `--demo` a `sistema:instalar` para cargar productos y clientes inventados.

## Actualizar una instalación existente
```bash
git pull
composer install --no-dev
php artisan migrate
php artisan optimize:clear
```

## Comandos útiles
| Comando | Para qué sirve |
|---|---|
| `sistema:instalar` | Prepara una instalación nueva |
| `sistema:verificar` | Revisa la instalación (producción, correo, respaldos, índices) |
| `roles:preparar [--restablecer]` | Crea los roles listos: Gerente, Cajero, Vendedor, Encargado de stock y Contador |
| `vendedor:clave` | Genera la clave del panel del vendedor (`VENDEDOR_CLAVE_HASH`) |
| `correo:probar tucorreo@ejemplo.com` | Prueba el envío de correo y explica si falla |
| `respaldo:base` | Respaldo de la base de datos |

## Correo de las demos
En el `.env` del servidor (nunca en `.env.example`) configurá `MAIL_MAILER=smtp` con los datos que cPanel muestra en
*Correo > Cuentas de correo > Connect Devices*. Usá el nombre de servidor saliente (Outgoing Server) tal cual aparece.
Después corré `php artisan optimize:clear` y probá desde el panel del vendedor en *Herramientas > Probar correo*.

## Pruebas
```bash
php artisan test
```

## Seguridad
- Nunca subas el archivo `.env` ni respaldos con datos reales a Git.
- En producción: `APP_ENV=production`, `APP_DEBUG=false` y HTTPS.
- Si una contraseña se publicó por error, cambiala de inmediato.

## Centro de Reportes (Excel y PDF)

Menú **Operaciones → Centro de Reportes**. Arriba tiene un **tablero** (ventas de hoy y del mes, ticket promedio, stock crítico, vencimientos, deuda vencida de clientes y a proveedores, diferencia de caja, timbrados) que muestra solo lo que el rol puede ver, con filtro por sucursal; cada tarjeta lleva al reporte que la explica. Abajo, los reportes (requiere el módulo `reportes_avanzados` y permisos). Cada reporte se filtra por fechas y sucursal y se descarga en **Excel (.xlsx)** o **PDF**. Con varias sucursales y "Todas" elegido, el Excel trae una hoja *Consolidado* más una hoja por sucursal, y el PDF una sección por sucursal.

- **Ventas** (incluye rendimiento por cajero/vendedor, ventas por usuario y día, por forma de pago y por producto): por período (día/mes con variación), por cajero, por forma de pago, por categoría, productos más vendidos, horas pico, días de la semana, listado de comprobantes con IVA, anuladas, devoluciones.
- **Stock**: valorizado (categoría/depósito), reposición bajo mínimo, rotación y cobertura, inmovilizado, próximos a vencer, kardex, ajustes y conteos.
- **Clientes y cartera**: cuentas por cobrar con antigüedad, estado de cuenta, cobranzas, mejores clientes, clientes inactivos, límites de crédito.
- **Compras y proveedores**: compras por período y por proveedor, listado, productos comprados, evolución del costo, anuladas, cuentas por pagar con antigüedad, facturas pendientes de pago y pagos realizados.
- **Fiscal**: libro IVA de ventas y de compras (con anuladas y devoluciones), resumen de IVA por mes (débito, crédito y saldo), ventas y compras por RUC, facturas emitidas por timbrado, numeración con saltos y vigencia de timbrados. Usa el mismo criterio de cálculo que el Libro IVA de Contabilidad; el IVA de compras se estima según el tipo de IVA de cada producto.
- **Caja y finanzas**: aperturas y cierres, faltantes y sobrantes, flujo de caja diario, movimientos por forma de pago e ingresos/egresos extra.

Permisos: `REPORTES_VENTAS`, `REPORTES_STOCK`, `REPORTES_CARTERA`, `REPORTES_COMPRAS`, `REPORTES_CAJA`, `REPORTES_FISCAL` (uno por área), `REPORTES_COSTOS` (ver costos, utilidad y margen; sin él esas columnas no aparecen ni en pantalla ni en los archivos) y `REPORTES_EXPORTAR` (descargar). Cada descarga queda en Auditoría. Los archivos se generan con código propio (sin librerías extra): `app/Reportes/Exportador.php`.

Al actualizar: `php artisan migrate` (carga los permisos nuevos y se los da a quien ya tenía `REPORTES_VER`, al Gerente y, para stock, al Encargado de stock) y `php artisan optimize:clear`.

## Unidades, códigos de barra y balanza

- Cada producto se vende por **Unidad**, **Kilo** o **Litro** (campo "Se vende por"). Precio, costo y stock van por kilo/litro; en caja se puede tipear en gramos/mililitros.
- **Códigos de barra:** en el formulario del producto, un código por línea (varios por producto). En el punto de venta, escanear + Enter selecciona el producto; buscar por nombre sigue funcionando igual.
- **Balanza con etiqueta:** cargá el **PLU** del producto (campo "Código de balanza"). Formato por defecto: `2` + PLU de 6 dígitos + 5 dígitos de valor + verificador. Se ajusta con las variables `BALANZA_*` del `.env` según el modelo de balanza.
- Tras actualizar: `php artisan migrate` (convierte cantidades a 3 decimales) y `php artisan optimize:clear`. Hacé backup antes.

## Padrón de RUC de la DNIT (autocompletar clientes)

1. Descargá los archivos del [listado de RUC de la DNIT](https://www.dnit.gov.py/web/portal-institucional/listado-de-ruc-con-sus-equivalencias).
2. Probá cómo se leen: `php artisan padron:importar archivo.zip --probar` (muestra las primeras líneas sin guardar).
3. Importá: `php artisan padron:importar ruc0.zip ruc1.zip ...` (se puede repetir para actualizar).
Al registrar un cliente (punto de venta o Clientes) escribís el RUC y se completa el nombre. En el buscador de clientes del PDV, al tipear un RUC aparecen también los del padrón.

## Instalar con la base inicial (sin pasar por las migraciones)

1. `createdb -U postgres dbStockNew`
2. `psql -U postgres -d dbStockNew -f dbstock_inicial.sql`  (estructura + permisos, roles, planes, sucursal, depósito, caja y categoría; sin usuarios)
3. Poné `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en el `.env` y corré `php artisan optimize:clear`
4. `php artisan sistema:instalar` → te pide usuario, correo y contraseña (mínimo 10 caracteres) y crea el administrador. No deja claves guardadas en ningún archivo.

Roles incluidos: Administrador (acceso total), Gerente, Cajero, Vendedor, Encargado de stock y Contador. Se ajustan desde "Gestión de Roles".
`PADRON_GESTIONAR` (importar/editar el padrón de RUC) lo tienen el Administrador y el Gerente; la consulta de RUC al cargar clientes la puede usar cualquiera que venda o gestione clientes.
Si ya tenías el sistema instalado, después de actualizar corré `php artisan permisos:sincronizar` y asignale `PADRON_GESTIONAR` a los roles que lo necesiten.
