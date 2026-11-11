<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/imagenes.php';
requerirAdmin();

$DIR_IMAGENES = __DIR__ . '/assets/productos';
$pdo = db();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $error = 'Sesión no válida. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';
        $id = (int) ($_POST['id'] ?? 0);

        if ($accion === 'guardar') {
            $sku = strtoupper(trim($_POST['sku'] ?? ''));
            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = (int) ($_POST['categoria_id'] ?? 0);
            $tipo = ($_POST['tipo'] ?? '') === 'servicio' ? 'servicio' : 'producto';
            $descripcion = trim($_POST['descripcion'] ?? '');
            $precio = (float) ($_POST['precio'] ?? 0);
            $oferta = trim($_POST['precio_oferta'] ?? '') === '' ? null : (float) $_POST['precio_oferta'];
            $stock = max(0, (int) ($_POST['stock'] ?? 0));

            // imagen: solo se reemplaza si el usuario sube un archivo nuevo
            $imagen = null;
            $huboSubida = isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK;
            if ($huboSubida) {
                $imagen = comprimirImagen($_FILES['imagen']['tmp_name'], $DIR_IMAGENES);
                if ($imagen === null) {
                    $error = 'El archivo de imagen no es valido. Usa JPG, PNG, GIF o WebP.';
                }
            }

            if (!$error) {
                if ($sku === '' || $nombre === '' || $categoria <= 0 || $precio < 0) {
                    $error = 'Completa SKU, nombre, categoría y un precio válido.';
                } elseif ($oferta !== null && $oferta > $precio) {
                    $error = 'El precio de oferta no puede ser mayor al precio normal.';
                } else {
                    try {
                        if ($id > 0) {
                            $anterior = $pdo->query('SELECT imagen FROM productos WHERE id = ' . $id)->fetch();
                            if ($huboSubida) {
                                $stmt = $pdo->prepare(
                                    'UPDATE productos SET sku=?, nombre=?, categoria_id=?, tipo=?, descripcion=?, imagen=?, precio=?, precio_oferta=?, stock=? WHERE id=?'
                                );
                                $stmt->execute([$sku, $nombre, $categoria, $tipo, $descripcion, $imagen, $precio, $oferta, $stock, $id]);
                                if ($anterior) {
                                    borrarImagen($anterior['imagen'], $DIR_IMAGENES);
                                }
                            } else {
                                $stmt = $pdo->prepare(
                                    'UPDATE productos SET sku=?, nombre=?, categoria_id=?, tipo=?, descripcion=?, precio=?, precio_oferta=?, stock=? WHERE id=?'
                                );
                                $stmt->execute([$sku, $nombre, $categoria, $tipo, $descripcion, $precio, $oferta, $stock, $id]);
                            }
                            $mensaje = 'Producto actualizado.';
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO productos (sku, nombre, categoria_id, tipo, descripcion, imagen, precio, precio_oferta, stock)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $stmt->execute([$sku, $nombre, $categoria, $tipo, $descripcion, $imagen, $precio, $oferta, $stock]);
                            $mensaje = 'Producto creado.';
                        }
                    } catch (PDOException $ex) {
                        $error = 'Ya existe un producto con ese SKU.';
                    }
                }
            }
        } elseif ($accion === 'stock' && $id > 0) {
            $ajuste = (int) ($_POST['ajuste'] ?? 0);
            $stmt = $pdo->prepare('UPDATE productos SET stock = GREATEST(0, stock + ?) WHERE id = ?');
            $stmt->execute([$ajuste, $id]);
            $mensaje = 'Stock actualizado.';
        } elseif ($accion === 'desactivar' && $id > 0) {
            $fila = $pdo->query('SELECT imagen FROM productos WHERE id = ' . $id)->fetch();
            $pdo->prepare('UPDATE productos SET activo = 0 WHERE id = ?')->execute([$id]);
            if ($fila) {
                borrarImagen($fila['imagen'], $DIR_IMAGENES);
            }
            $mensaje = 'Producto desactivado.';
        }
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([(int) $_GET['editar']]);
    $editando = $stmt->fetch() ?: null;
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
$filtroCat = (int) ($_GET['categoria'] ?? 0);
$sql = 'SELECT p.*, c.nombre AS categoria FROM productos p JOIN categorias c ON c.id = p.categoria_id WHERE p.activo = 1';
$params = [];
if ($filtroCat > 0) {
    $sql .= ' AND p.categoria_id = ?';
    $params[] = $filtroCat;
}
$sql .= ' ORDER BY c.nombre, p.nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$f = $editando ?? ['id' => 0, 'sku' => '', 'nombre' => '', 'categoria_id' => '', 'tipo' => 'producto', 'descripcion' => '', 'imagen' => '', 'precio' => '', 'precio_oferta' => '', 'stock' => 0];

render('Productos', '');
?>
<h1>Inventario y precios</h1>

<?php if ($mensaje): ?><p class="alert ok"><?= e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>

<section class="card">
    <h2><?= $editando ? 'Editar producto' : 'Nuevo producto' ?></h2>
    <form method="post" class="grid-form" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <label>SKU <input type="text" name="sku" value="<?= e($f['sku']) ?>" required></label>
        <label>Nombre <input type="text" name="nombre" value="<?= e($f['nombre']) ?>" required></label>
        <label>Categoría
            <select name="categoria_id" required>
                <option value="">Selecciona...</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) $f['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Tipo
            <select name="tipo">
                <option value="producto" <?= $f['tipo'] === 'producto' ? 'selected' : '' ?>>Producto (controla stock)</option>
                <option value="servicio" <?= $f['tipo'] === 'servicio' ? 'selected' : '' ?>>Servicio</option>
            </select>
        </label>
        <label>Precio normal <input type="number" name="precio" step="0.01" min="0" value="<?= e($f['precio']) ?>" required></label>
        <label>Precio oferta <input type="number" name="precio_oferta" step="0.01" min="0" value="<?= e($f['precio_oferta']) ?>"></label>
        <label>Stock <input type="number" name="stock" min="0" value="<?= (int) $f['stock'] ?>"></label>
        <label class="ancho">Descripción <input type="text" name="descripcion" value="<?= e($f['descripcion']) ?>"></label>
        <label class="ancho">Imagen del producto (se comprime automáticamente)
            <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php if (!empty($f['imagen'])): ?>
                <img src="<?= e(rutaImagen($f['imagen'])) ?>" alt="Imagen actual" style="max-height:70px;border-radius:6px;margin-top:6px">
                <small>Deja el campo vacío para conservar la imagen actual.</small>
            <?php endif; ?>
        </label>
        <div class="acciones">
            <?php if ($editando): ?><a class="btn" href="productos.php">Cancelar</a><?php endif; ?>
            <button type="submit" class="btn primary">Guardar</button>
        </div>
    </form>
</section>

<form method="get" class="toolbar">
    <label>Categoría
        <select name="categoria" onchange="this.form.submit()">
            <option value="0">Todas</option>
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $filtroCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
</form>

<table class="tabla">
    <thead>
        <tr><th>Imagen</th><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Oferta</th><th>Stock</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($productos as $p): ?>
        <tr>
            <td><?php if (!empty($p['imagen'])): ?><img src="<?= e(rutaImagen($p['imagen'])) ?>" alt="" class="thumb"><?php else: ?><span class="thumb thumb-vacio">—</span><?php endif; ?></td>
            <td><?= e($p['sku']) ?></td>
            <td><?= e($p['nombre']) ?><?= $p['tipo'] === 'servicio' ? ' <small>(servicio)</small>' : '' ?></td>
            <td><?= e($p['categoria']) ?></td>
            <td><?= moneda((float) $p['precio']) ?></td>
            <td><?= $p['precio_oferta'] !== null ? moneda((float) $p['precio_oferta']) : '—' ?></td>
            <td>
                <?php if ($p['tipo'] === 'producto'): ?>
                <form method="post" class="inline">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="accion" value="stock">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button name="ajuste" value="-1" class="btn mini">−</button>
                    <strong><?= (int) $p['stock'] ?></strong>
                    <button name="ajuste" value="1" class="btn mini">+</button>
                </form>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td class="acciones-fila">
                <a href="productos.php?editar=<?= (int) $p['id'] ?>">Editar</a>
                <form method="post" class="inline" onsubmit="return confirm('¿Desactivar este producto?')">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="accion" value="desactivar">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="link-danger">Desactivar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php renderFin(); ?>
