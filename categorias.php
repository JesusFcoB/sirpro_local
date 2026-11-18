<?php
require __DIR__ . '/includes/bootstrap.php';
requerirAdmin();

$pdo = db();
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido($_POST['csrf'] ?? null)) {
        $error = 'Sesión no válida. Recarga la página.';
    } else {
        $accion = $_POST['accion'] ?? '';
        $id = idValido($_POST['id'] ?? 0);
        $nombre = textoValido($_POST['nombre'] ?? '', 100);

        if ($accion === 'guardar') {
            if ($nombre === '') {
                $error = 'El nombre de la categoría es obligatorio.';
            } elseif ($id > 0) {
                $stmt = $pdo->prepare('SELECT id FROM categorias WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() === false) {
                    $error = 'La categoría ya no existe. Recarga la página.';
                }
            }

            if (!$error) {
                try {
                    if ($id > 0) {
                        $pdo->prepare('UPDATE categorias SET nombre = ? WHERE id = ?')->execute([$nombre, $id]);
                        $mensaje = 'Categoría actualizada.';
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO categorias (nombre) VALUES (?)');
                        $stmt->execute([$nombre]);
                        $id = (int) $pdo->lastInsertId();
                        $mensaje = 'Categoría creada.';
                    }
                    flash($mensaje);
                    irA('categorias.php' . ($id > 0 ? '?editar=' . $id : ''));
                } catch (PDOException $ex) {
                    $error = 'Ya existe una categoría con ese nombre.';
                }
            }
        } elseif ($accion === 'eliminar' && $id > 0) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE categoria_id = ?');
            $stmt->execute([$id]);
            $total = (int) $stmt->fetchColumn();
            if ($total > 0) {
                $error = 'No se puede eliminar: hay ' . $total . ' producto(s) en esta categoría.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM categorias WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->fetchColumn() === false) {
                    $error = 'La categoría ya no existe.';
                } else {
                    $pdo->prepare('DELETE FROM categorias WHERE id = ?')->execute([$id]);
                    flash('Categoría eliminada.');
                    irA('categorias.php');
                }
            }
        } else {
            $error = 'Acción no reconocida.';
        }
    }
}

$editando = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare('SELECT * FROM categorias WHERE id = ?');
    $stmt->execute([idValido($_GET['editar'])]);
    $editando = $stmt->fetch() ?: null;
}

$categorias = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id) AS productos
     FROM categorias c ORDER BY c.nombre'
)->fetchAll();
$f = $editando ?? ['id' => 0, 'nombre' => ''];

render('Categorías', '');
?>
<h1>Categorías</h1>

<?php if ($mensaje): ?><p class="alert ok"><?= e($mensaje) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
<?php renderMensajes(); ?>

<section class="card">
    <h2><?= $editando ? 'Editar categoría' : 'Nueva categoría' ?></h2>
    <form method="post" class="grid-form" style="grid-template-columns: 1fr auto auto;">
        <?= csrfCampo() ?>
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
        <label>Nombre <input type="text" name="nombre" maxlength="100" value="<?= e($f['nombre']) ?>" required></label>
        <div class="acciones" style="align-self: end;">
            <?php if ($editando): ?><a class="btn secondary" href="categorias.php">Cancelar</a><?php endif; ?>
            <button type="submit" class="btn success">Guardar</button>
        </div>
    </form>
</section>

<table class="tabla">
    <thead>
        <tr><th>Nombre</th><th>Productos</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($categorias as $c): ?>
        <tr>
            <td><?= e($c['nombre']) ?></td>
            <td><?= (int) $c['productos'] ?></td>
            <td class="acciones-fila">
                <a href="categorias.php?editar=<?= (int) $c['id'] ?>">Editar</a>
                <form method="post" class="inline" onsubmit="return confirm('¿Se elimina esta categoría?')">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button type="submit" class="link-danger">Eliminar</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($categorias === []): ?>
        <tr><td colspan="3" class="vacio">No hay categorías.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php renderFin(); ?>
