# Sirpro - Punto de venta (PHP + MySQL)

Sistema de punto de venta local para Sirpro. Incluye:

- **Login** con roles (`admin` y `cajero`).
- **Punto de venta**: búsqueda y filtro por categoría, carrito, descuentos por precio de oferta, cobro en efectivo/tarjeta/transferencia y cálculo de cambio.
- **Control de stock**: al cobrar se descuenta el inventario dentro de una transacción (no permite vender más de lo disponible).
- **Ventas**: historial por fecha con total del periodo y acceso al ticket.
- **Ticket** imprimible.
- **Inventario y precios** (solo admin): alta/edición de productos, ajuste de stock, precios de oferta y desactivación.
- **Categorías** (solo admin): CRUD completo para gestionar categorías.
- **Reportes** (solo admin): exporta productos, categorías y ventas a Excel (CSV) o imprime en PDF.
- **Fotos de productos** con compresión automática: al subir una imagen se reduce a máximo 800 px y se convierte a WebP (puede pasar de 3 MB a ~50 KB sin perder calidad visible).
- **Categorías** tomadas del documento de requerimientos: Laptops, Computadoras de Escritorio, Impresoras, Discos SSD, Periféricos, Memorias USB y Servicios (mantenimiento de impresoras, cambio de pantalla, instalación de Office, etc.).

## Requisitos

- XAMPP instalado (se usan su PHP y su MySQL).

## Inicio rápido (recomendado)

1. Haz doble clic en `iniciar_sirpro.bat`.
   - Inicia MySQL (solo si no estaba corriendo).
   - Crea la base de datos `sirpro` automáticamente la primera vez.
   - Levanta el servidor web local en el puerto 8000.
   - Abre el navegador en el punto de venta.
2. Cuando termines, **presiona una tecla en la ventana del script**. Este detiene el servidor web y MySQL, y se cierra. No cierres esa ventana con la X; si lo haces, los servicios seguirán encendidos.

Opciones del script:

- `iniciar_sirpro.bat /nobrowser` — hace lo mismo pero sin abrir el navegador.
- `iniciar_sirpro.bat /auto` — modo prueba: cierra solo a los 5 segundos (útil para verificar el ciclo completo).
- Si MySQL ya estaba corriendo (por ejemplo desde el panel de XAMPP), lo reutiliza y **no lo apaga** al cerrar.

## Instalación manual

1. Inicia **MySQL** desde el panel de XAMPP.
2. Abre phpMyAdmin (http://localhost/phpmyadmin), ve a **Importar** y selecciona `database.sql`. Esto crea la base `sirpro` con datos de ejemplo.
3. Para ver el proyecto, elige una opción:
   - **Opción A:** copia la carpeta `sirpro` a `C:\xampp\htdocs\sirpro` y abre http://localhost/sirpro
   - **Opción B:** desde esta carpeta ejecuta
     ```
     C:\xampp\php\php.exe -S localhost:8000
     ```
     y abre http://localhost:8000

## Acceso inicial

- Usuario: `admin`
- Contraseña: `admin123`

Cambia la contraseña en cuanto entres (se puede hacer desde la base de datos con un hash de `password_hash`).

## Configuración

Si tu MySQL tiene usuario o contraseña distintos a `root` sin contraseña, edita las constantes `DB_*` en `includes/bootstrap.php`.

## Logo

- `assets/logo.svg` — logo vectorial incluido (se ve nítido en pantalla e impresión).
- Si colocas el logo oficial como `assets/logo.png`, el sistema lo usa automáticamente en lugar del SVG.

## Imágenes de productos

- En **Productos** verás el campo "Imagen del producto". Acepta JPG, PNG, GIF o WebP.
- La imagen se comprime automáticamente al subirla: se reduce a un máximo de 800 px de lado y se guarda en formato WebP dentro de `assets/productos/`.
- Deja el campo vacío al editar para conservar la imagen actual. Al desactivar un producto, su imagen se elimina.
- **Si ya tenías la base de datos creada antes de esta función**, ejecuta `database_imagenes.sql` en phpMyAdmin para agregar la columna de imagen. Las instalaciones nuevas no lo necesitan.

## Estructura

```
index.php          Redirección inicial
login.php          Inicio de sesión
logout.php         Cierre de sesión
pos.php            Pantalla de punto de venta
ticket.php         Ticket de venta imprimible
ventas.php         Historial de ventas
productos.php      Inventario y precios (admin)
categorias.php     Gestión de categorías (admin)
reportes.php       Menú de reportes (admin)
reporte_*.php      Reportes de productos, categorías y ventas
api/venta.php      Registro de venta (transaccional)
includes/          Conexión, sesión y plantilla
assets/            Estilos y logo
database.sql       Esquema y datos de ejemplo
```

## Próximos pasos sugeridos

- Gestión de usuarios (alta de cajeros).
- Reportes de ventas por producto y corte de caja.
- Módulo de cotizaciones para equipos a la medida (CCTV, PCs ensambladas).
- Módulo de catálogo público para la página web según el documento de requerimientos.
