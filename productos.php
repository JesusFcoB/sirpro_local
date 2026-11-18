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
        $id = idValido($_POST['id'] ?? 0);

        if ($accion === 'guardar') {
            $sku = strtoupper(textoValido($_POST['sku'] ?? '', 50));
            $nombre = textoValido($_POST['nombre'] ?? '', 150);
            $categoria = idValido($_POST['categoria_id'] ?? 0);
            $tipo = (($_POST['tipo'] ?? '') === 'servicio') ? 'servicio' : 'producto';
            $descripcion = textoValido($_POST['descripcion'] ?? '', 500);
            $precio = numeroValido($_POST['precio'] ?? null, 0, 100000000);
            $ofertaTexto = trim((string) ($_POST['precio_oferta'] ?? ''));
            $oferta = $ofertaTexto === '' ? null : numeroValido($ofertaTexto, 0, 100000000);
            $stock = numeroValido($_POST['stock'] ?? 0, 0, 1000000);
            $minimo = numeroValido($_POST['stock_minimo'] ?? 5, 0, 1000000);

            if ($precio === null || $stock === null || $minimo === null || ($ofertaTexto !== '' && $oferta === null)) {
                $error = 'Revisa los valores numéricos: precio, stock y mínimo deben ser válidos.';
            } elseif ($sku === '' || $nombre === '' || $categoria <= 0) {
                $error = 'Completa SKU, nombre y categoría.';
            } elseif ($oferta !== null && $oferta > $precio) {
                $error = 'El precio de oferta no puede ser mayor al precio normal.';
            } else {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM categorias WHERE id = ?');
                $stmt->execute([$categoria]);
                if ((int) $stmt->fetchColumn() === 0) {
                    $error = 'La categoría seleccionada ya no existe. Recarga la página.';
                }
            }

            $imagen = null;
            $huboSubida = isset($_FILES['imagen'])
                && is_array($_FILES['imagen'])
                && (int) ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

            if (!$error && $huboSubida) {
                if ((int) ($_FILES['imagen']['size'] ?? 0) > 5 * 1024 * 1024) {
                    $error = 'La imagen debe pesar menos de 5 MB.';
                } else {
                    $imagen = comprimirImagen($_FILES['imagen']['tmp_name'], $DIR_IMAGENES);
                    if ($imagen === null) {
                        $error = 'El archivo de imagen no es válido. Usa JPG, PNG, GIF o WebP.';
                    }
                }
            }

            if (!$error) {
                try {
                    $anterior = null;
                    if ($id > 0) {
                        $stmt = $pdo->prepare('SELECT imagen FROM productos WHERE id = ?');
                        $stmt->execute([$id]);
                        $anterior = $stmt->fetch() ?: null;
                        if ($anterior === null) {
                            $error = 'El producto ya no existe.';
                        }
                    }

                    if (!$error) {
                        if ($id > 0) {
                            if ($huboSubida) {
                                $stmt = $pdo->prepare(
                                    'UPDATE productos SET sku=?, nombre=?, categoria_id=?, tipo=?, descripcion=?,
                                     imagen=?, precio=?, precio_oferta=?, stock=?, stock_minimo=? WHERE id=?'
                                );
                                $stmt->execute([$sku, $nombre, $categoria, $tipo, $descripcion, $imagen,
                                    $precio, $oferta, (int) $stock, (int) $minimo, $id]);
                                if ($anterior) {
                                    borrarImagen($anterior['imagen'], $DIR_IMAGENES);
                                }
                            } else {
                                $stmt = $pdo->prepare(
                                    'UPDATE productos SET sku=?, nombre=?, categoria_id=?, tipo=?, descripcion=?,
                                     precio=?, precio_oferta=?, stock=?, stock_minimo=? WHERE id=?'
                                );
                                $stmt->execute([$sku, $nombre, $categoria, $tipo, $descripcion,
                                    $precio, $oferta, (int) $stock, (int) $minimo, $id]);
                            }
                            $mensaje = 'Producto actualizado.';
                        } else {
                            $stmt = $pdo->prepare(
                                'INSERT INTO productos (sku, nombre, categoria_id, tipo, descripcion, imagen,
                                 precio, precio_oferta, stock, stock_minimo)
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                            );
                            $stmt->execute([$sku, $nombre, $categoria, $tipo, $descripcion, $imagen,
                                $precio, $oferta, (int) $stock, (int) $minimo]);
                            $id = (int) $pdo->lastInsertId();
                            $mensaje = 'Producto creado.';
                        }
                        flash($mensaje);
                        irA('productos.php' . ($id > 0 ? '?editar=' . $id : ''));
                    }
                } catch (PDOException $ex) {
                    $error = 'No se pudo guardar. Revisa que el SKU no esté repetido.';
                }
            }
        } elseif ($accion === 'stock' && $id > 0) {
            $ajuste = filter_var((string) ($_POST['ajuste'] ?? ''), FILTER_VALIDATE_INT);
            if (!in_array($ajuste, [-1, 1], true)) {
                $error = 'Ajuste de stock no válido.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM productos WHERE id = ? AND activo = 1');
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() === false) {
                    $error = 'El producto ya no existe.';
                } else {
                    $pdo->prepare('UPDATE productos SET stock = GREATEST(0, stock + ?) WHERE id = ?')
                        ->execute([$ajuste, $id]);
                    flash('Stock actualizado.');
                    irA('productos.php');
                }
            }
        } elseif ($accion === 'desactivar' && $id > 0) {
            $stmt = $pdo->prepare('SELECT imagen FROM productos WHERE id = ? AND activo = 1');
            $stmt->execute([$id]);
            $fila = $stmt->fetch() ?: null;
            if ($fila === null) {
                $error = 'El producto ya no existe.';
            } else {
                $pdo->prepare('UPDATE productos SET activo = 0 WHERE id = ?')->execute([$id]);
                borrarImagen($fila['imagen'], $DIR_IMAGENES);
                flash('Producto desactivado.');
                irA('productos.php');
            }
        } else {
            $error = 'Acción no reconocida.';
        }
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $stmt->execute([idValido($_GET['editar'])]);
    $editando = $stmt->fetch() ?: null;
}

$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();
$filtroCat = idValido($_GET['categoria'] ?? 0);
$sql = 'SELECT p.*, c.nombre AS categoria FROM productos p
        JOIN categorias c ON c.id = p.categoria_id WHERE p.activo = 1';
$params = [];
if ($filtroCat > 0) {
    $sql .= ' AND p.categoria_id = ?';
    $params[] = $filtroCat;
}
$sql .= ' ORDER BY c.nombre, p.nombre';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

$resumenStock = $pdo->query(
    "SELECT SUM(stock <= 0) AS agotados, SUM(stock > 0 AND stock <= stock_minimo) AS bajos
     FROM productos WHERE activo = 1 AND tipo = 'producto'"
)->fetch();

$f = $editando ?? [
    'id' => 0, 'sku' => '', 'nombre' => '', 'categoria_id' => '', 'tipo' => 'producto',
    'descripcion' => '', 'imagen' => '', 'precio' => '', 'precio_oferta' => '',
    'stock' => 0, 'stock_minimo' => 5,
];

render('Productos', '');
?>
<h1>Inventario y precios</h1>

<?php if ($mensaje): ?><p class="alert ok"><?= e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
<?php renderMensajes(); ?>

<?php if ((int) ($resumenStock['agotados'] ?? 0) > 0 || (int) ($resumenStock['bajos'] ?? 0) > 0): ?>
    <p class="alert warning">
        ⚠️ Hay <?= (int) $resumenStock['agotados'] ?> producto(s) agotado(s) y
        <?= (int) $resumenStock['bajos'] ?> con stock bajo. Repón antes de vender.
    </p>
<?php endif; ?>

<section class="card">
    <h2><?= $editando ? 'Editar producto' : 'Nuevo producto' ?></h2>
    <form method="post" class="grid-form" enctype="multipart/form-data">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <label>SKU <input type="text" name="sku" maxlength="50" value="<?= e($f['sku']) ?>" required></label>
        <label>Nombre <input type="text" name="nombre" maxlength="150" value="<?= e($f['nombre']) ?>" required></label>
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
        <label>Precio normal <input type="number" name="precio" step="0.01" min="0" max="100000000" value="<?= e($f['precio']) ?>" required></label>
        <label>Precio oferta <input type="number" name="precio_oferta" step="0.01" min="0" max="100000000" value="<?= e($f['precio_oferta']) ?>"></label>
        <label>Stock actual <input type="number" name="stock" min="0" max="1000000" value="<?= (int) $f['stock'] ?>"></label>
        <label>Stock mínimo (alerta) <input type="number" name="stock_minimo" min="0" max="1000000" value="<?= (int) ($f['stock_minimo'] ?? 5) ?>"></label>
        <label class="ancho">Descripción
            <span class="ia-desc-fila">
                <input type="text" name="descripcion" maxlength="500" value="<?= e($f['descripcion']) ?>">
                <button id="ia-descripcion-btn" class="btn secondary mini" type="button">✨ Generar con IA</button>
            </span>
            <small id="ia-descripcion-estado" class="ia-desc-estado"></small>
        </label>
        <label class="ancho">Imagen del producto (se comprime automáticamente, máx. 5 MB)
            <input type="file" name="imagen" accept="image/jpeg,image/png,image/gif,image/webp">
            <?php if (!empty($f['imagen'])): ?>
                <img src="<?= e(rutaImagen($f['imagen'])) ?>" alt="Imagen actual" style="max-height:70px;border-radius:6px;margin-top:6px">
                <small>Deja el campo vacío para conservar la imagen actual.</small>
            <?php endif; ?>
        </label>
        <div class="acciones">
            <?php if ($editando): ?><a class="btn secondary" href="productos.php">Cancelar</a><?php endif; ?>
            <button type="submit" class="btn success">Guardar</button>
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
        <tr><th>Imagen</th><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Oferta</th><th>Stock</th><th>Estado</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($productos as $p): ?>
        <?php $minimo = (int) ($p['stock_minimo'] ?? 5); ?>
        <tr>
            <td><?php if (!empty($p['imagen'])): ?><img src="<?= e(rutaImagen($p['imagen'])) ?>" alt="" class="thumb"><?php else: ?><span class="thumb thumb-vacio">—</span><?php endif; ?></td>
            <td><?= e($p['sku']) ?></td>
            <td><?= e($p['nombre']) ?></td>
            <td><?= e($p['categoria']) ?></td>
            <td><?= moneda((float) $p['precio']) ?></td>
            <td><?= $p['precio_oferta'] !== null ? moneda((float) $p['precio_oferta']) : '—' ?></td>
            <td>
                <?php if ($p['tipo'] === 'producto'): ?>
                <form method="post" class="inline">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="stock">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button name="ajuste" value="-1" class="btn mini danger" title="Quitar 1">−</button>
                    <strong><?= (int) $p['stock'] ?></strong>
                    <button name="ajuste" value="1" class="btn mini info" title="Agregar 1">+</button>
                </form>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= badgeStock((int) $p['stock'], $minimo, $p['tipo']) ?><small>mín. <?= $minimo ?></small></td>
            <td class="acciones-fila">
                <a href="productos.php?editar=<?= (int) $p['id'] ?>">Editar</a>
                <form method="post" class="inline" onsubmit="return confirm('¿Desactivar este producto? No se podrá vender.')">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="desactivar">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="link-danger">Desactivar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($productos === []): ?>
        <tr><td colspan="9" class="vacio">No hay productos en esta categoría.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

<script>
(function () {
    'use strict';
    var boton = document.getElementById('ia-descripcion-btn');
    var estado = document.getElementById('ia-descripcion-estado');
    if (!boton) return;
    var form = boton.closest('form');
    var csrf = <?php echo json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function campo(nombre) { return form.querySelector('[name="' + nombre + '"]'); }

    boton.addEventListener('click', async function () {
        var nombre = (campo('nombre') || {}).value || '';
        if (!nombre.trim()) {
            estado.textContent = 'Escribí primero el nombre del producto.';
            return;
        }
        var categoriaSelect = campo('categoria_id');
        var categoria = categoriaSelect && categoriaSelect.selectedIndex > 0
            ? categoriaSelect.options[categoriaSelect.selectedIndex].text : '';
        boton.disabled = true;
        boton.textContent = 'Generando…';
        estado.textContent = '';
        try {
            var r = await fetch('api/ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf: csrf,
                    accion: 'descripcion',
                    nombre: nombre,
                    categoria: categoria,
                    precio: (campo('precio') || {}).value || '',
                    tipo: (campo('tipo') || {}).value || 'producto'
                })
            });
            var datos = await r.json();
            if (!r.ok || datos.error) throw new Error(datos.error || 'No se pudo generar la descripción.');
            campo('descripcion').value = datos.descripcion;
            estado.textContent = 'Descripción generada. Revisala antes de guardar.';
        } catch (e) {
            estado.textContent = e.message || 'No se pudo generar la descripción.';
        } finally {
            boton.disabled = false;
            boton.textContent = '✨ Generar con IA';
        }
    });
})();
</script>
<?php renderFin(); ?>
