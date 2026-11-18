# Sirpro - Punto de venta (PHP + MySQL)

Sistema de punto de venta local para Sirpro. Incluye:

- **Login** con roles (`admin` y `cajero`).
- **Punto de venta**: búsqueda y filtro por categoría, carrito, descuentos por precio de oferta, cobro en efectivo/tarjeta/transferencia y cálculo de cambio.
- **Control de stock**: al cobrar se descuenta el inventario dentro de una transacción (no permite vender más de lo disponible).
- **Tablero**: ventas de hoy/mes, valor de inventario, productos activos, stock bajo, agotados y últimas ventas.
- **Alertas de stock**: cada producto tiene un stock mínimo; el tablero, el inventario y el punto de venta avisan cuando una existencia está por debajo de ese nivel (sin permitir vender más de lo disponible).
- **Ventas**: historial por fecha con total del periodo y acceso al ticket.
- **Ticket** imprimible.
- **Inventario y precios** (solo admin): alta/edición de productos, ajuste de stock, precios de oferta y desactivación.
- **Categorías** (solo admin): CRUD completo para gestionar categorías.
- **Reportes** (solo admin): exporta productos, categorías y ventas a Excel con formato (`.xls`: logo y membrete como en el PDF, encabezados, anchos, números y totales) o abre una hoja lista para imprimir/guardar como PDF.
- **Modo oscuro**: botón 🌙/☀️ en la barra superior; recuerda la preferencia y mantiene legibles textos, tablas, alertas y hojas de impresión.
- **Operaciones más seguras**: token CSRF en todos los formularios, validación estricta de ids/fechas/montos, comprobación de categorías, límites de subida de imágenes, confirmación antes de cobrar y redirección después de guardar para evitar duplicar acciones con F5.
- **Fotos de productos** con compresión automática: al subir una imagen se reduce a máximo 800 px y se convierte a WebP (puede pasar de 3 MB a ~50 KB sin perder calidad visible).
- **Categorías** tomadas del documento de requerimientos: Laptops, Computadoras de Escritorio, Impresoras, Discos SSD, Periféricos, Memorias USB y Servicios (mantenimiento de impresoras, cambio de pantalla, instalación de Office, etc.).
- **Asistente IA** con Gemini: chat disponible para todos los usuarios con tres modos (analizar negocio, ayuda del sistema y redactar textos). Incluye respuestas en streaming con botón **Detener**, copiar/regenerar, caché de respuestas frecuentes y límite de uso por usuario. Se configura una sola vez en el servidor; no requiere instalar nada en las PC.
- **Reloj en la barra superior**: muestra la fecha y la hora locales en español de Latam y se actualiza cada segundo.
- **Sugerencia de reposición** en el tablero: prioriza productos con stock bajo usando también las ventas de los últimos 30 días, con un botón para pedirle más detalles a la IA.
- **Generar descripción con IA** en el formulario de Productos: redacta una descripción a partir del nombre, categoría y precio.

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

## Asistente IA (Gemini)

1. Obtén una clave de Google AI Studio: https://aistudio.google.com/apikey (plan gratuito).
2. Copia `includes/config_ia.example.php` a `includes/config_ia.php` y rellena el campo `clave`.
3. Ingresa a Sirpro y abre **Asistente IA** en el menú. El indicador debe decir **IA lista**.
4. Opciones de `includes/config_ia.php`:
   - `clave`: tu API key de Google AI Studio.
   - `modelo`: modelo Gemini principal (por defecto `gemini-3.5-flash-lite`, más ligero y estable).
   - `modelos_alternativos`: si el principal está saturado o da timeout, se prueba automáticamente con estos modelos (por defecto `gemini-3.8-flash`).
   - `timeout`: segundos máximos de espera por respuesta y por modelo.
   - `incluir_datos`: si es `false`, el modo **Analizar negocio** no envía cifras de ventas ni inventario a internet.
   - `limite_por_minuto` y `limite_por_hora`: máximo de preguntas por usuario (por defecto 8 y 40). Protegen tu clave de Gemini contra uso accidental o spam.

La clave queda en el servidor y nunca se envía al navegador. `includes/config_ia.php` está en `.gitignore` para que no se suba al repositorio. Si no hay clave o no hay internet, el resto de Sirpro sigue funcionando y el asistente muestra un aviso.

El modo **Analizar negocio** es solo para administradores. **Ayuda del sistema** y **Redactar textos** están disponibles para todos los usuarios.

En **Productos**, el botón **✨ Generar con IA** redacta la descripción y la ajusta automáticamente: corrige espacios y formato, agrega mayúscula inicial y punto final, y la recorta sin cortar palabras para que no supere los 500 caracteres del campo. Siempre conviene revisarla antes de guardar.

## Logo

- `assets/logo.svg` — logo vectorial incluido (se ve nítido en pantalla e impresión).
- Si colocas el logo oficial como `assets/logo.png`, el sistema lo usa automáticamente en lugar del SVG.

## Imágenes de productos

- En **Productos** ves el campo "Imagen del producto". Acepta JPG, PNG, GIF o WebP.
- La imagen se comprime automáticamente al subirla: se reduce a un máximo de 800 px de lado y se guarda en formato WebP dentro de `assets/productos/`.
- Deja el campo vacío al editar para conservar la imagen actual. Al desactivar un producto, su imagen se elimina.
- **Si ya tienes la base de datos creada antes de esta función**, ejecuta `database_imagenes.sql` en phpMyAdmin para agregar la columna de imagen. Las instalaciones nuevas no lo necesitan.

## Alertas de stock

- Cada producto tiene un campo **Stock mínimo** (por defecto 5). Cuando el stock está entre 1 y el mínimo se marca como **Stock bajo**; cuando llega a 0, como **Agotado**.
- Los servicios no generan alertas porque no controlan inventario.
- **Si ya tienes la base de datos creada antes de esta función**, ejecuta `database_stock.sql` en phpMyAdmin (o desde la línea de comandos de MySQL) para agregar la columna `stock_minimo`. Es seguro ejecutarlo más de una vez. Las instalaciones nuevas no lo necesitan.

## Estructura

```
index.php          Redirección inicial (tablero o login)
login.php          Inicio de sesión
logout.php         Cierre de sesión
dashboard.php      Tablero con KPIs y alertas de stock
pos.php            Pantalla de punto de venta
ticket.php         Ticket de venta imprimible
ventas.php         Historial de ventas
productos.php      Inventario y precios (admin)
categorias.php     Gestión de categorías (admin)
reportes.php       Menú de reportes (admin)
reporte_*.php      Reportes de productos, categorías y ventas
api/venta.php      Registro de venta (transaccional)
ia.php             Asistente IA (chat)
api/ia.php         Endpoint del asistente IA
includes/          Conexión, sesión, plantilla, exportador de Excel y configuración de IA
assets/            Estilos y logo
database.sql       Esquema y datos de ejemplo
database_stock.sql Migración de stock mínimo (instalaciones existentes)
includes/config_ia.example.php  Ejemplo de configuración del asistente IA
```

## Próximos pasos sugeridos

- Gestión de usuarios (alta de cajeros).
- Reportes de ventas por producto y corte de caja.
- Módulo de cotizaciones para equipos a la medida (CCTV, PCs ensambladas).
- Módulo de catálogo público para la página web según el documento de requerimientos.
