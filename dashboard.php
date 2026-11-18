<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/ia.php';
requerirLogin();

$pdo = db();
$sugerencia = iaSugerenciaLocal();

$hoy = $pdo->query(
    'SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM ventas WHERE DATE(created_at) = CURDATE()'
)->fetch();
$mes = $pdo->query(
    "SELECT COUNT(*) AS n, COALESCE(SUM(total), 0) AS t FROM ventas
     WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')"
)->fetch();
$inventario = $pdo->query(
    "SELECT COUNT(*) AS n, COALESCE(SUM(stock * COALESCE(precio_oferta, precio)), 0) AS v
     FROM productos WHERE activo = 1 AND tipo = 'producto'"
)->fetch();
$categorias = (int) $pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();
$activos = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1")->fetchColumn();

$conteo = $pdo->query(
    "SELECT
        SUM(stock <= 0) AS agotados,
        SUM(stock > 0 AND stock <= stock_minimo) AS bajos
     FROM productos WHERE activo = 1 AND tipo = 'producto'"
)->fetch();
$agotados = (int) ($conteo['agotados'] ?? 0);
$bajos = (int) ($conteo['bajos'] ?? 0);

$alertas = $pdo->query(
    "SELECT sku, nombre, stock, stock_minimo FROM productos
     WHERE activo = 1 AND tipo = 'producto' AND stock <= stock_minimo
     ORDER BY stock ASC, nombre ASC LIMIT 8"
)->fetchAll();

$ultimas = $pdo->query(
    'SELECT v.id, v.folio, v.total, v.created_at, u.nombre AS cajero
     FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
     ORDER BY v.created_at DESC LIMIT 5'
)->fetchAll();

render('Tablero', '');
renderMensajes();
?>
<h1>Tablero</h1>
<p class="ayuta-filtro">Resumen del negocio al <?= e(date('d/m/Y H:i')) ?></p>

<div class="kpis">
    <div class="kpi">
        <span class="etiqueta">Ventas de hoy</span>
        <span class="valor"><?= (int) $hoy['n'] ?></span>
        <span class="detalle"><?= moneda((float) $hoy['t']) ?></span>
    </div>
    <div class="kpi verde">
        <span class="etiqueta">Ventas del mes</span>
        <span class="valor"><?= (int) $mes['n'] ?></span>
        <span class="detalle"><?= moneda((float) $mes['t']) ?></span>
    </div>
    <div class="kpi">
        <span class="etiqueta">Productos activos</span>
        <span class="valor"><?= $activos ?></span>
        <span class="detalle"><?= $categorias ?> categoría(s)</span>
    </div>
    <div class="kpi info-kpi">
        <span class="etiqueta">Valor de inventario</span>
        <span class="valor"><?= moneda((float) $inventario['v']) ?></span>
        <span class="detalle"><?= (int) $inventario['n'] ?> producto(s)</span>
    </div>
    <div class="kpi alerta">
        <span class="etiqueta">Stock bajo</span>
        <span class="valor"><?= $bajos ?></span>
        <span class="detalle">Por reponer</span>
    </div>
    <div class="kpi peligro">
        <span class="etiqueta">Agotados</span>
        <span class="valor"><?= $agotados ?></span>
        <span class="detalle">Sin existencias</span>
    </div>
</div>

<div class="dos-col">
    <section class="card">
        <h2>⚠️ Alertas de inventario</h2>
        <?php if ($alertas === []): ?>
            <p class="alert ok">Todo en orden: ningún producto está por debajo de su stock mínimo.</p>
        <?php else: ?>
            <ul class="lista-alertas">
                <?php foreach ($alertas as $a): ?>
                    <li>
                        <span>
                            <span class="nombre"><?= e($a['nombre']) ?></span>
                            <span class="sku"><?= e($a['sku']) ?> · mínimo <?= (int) $a['stock_minimo'] ?></span>
                        </span>
                        <?= badgeStock((int) $a['stock'], (int) $a['stock_minimo'], 'producto') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
                <div class="acciones">
                    <a class="btn warning" href="productos.php">Revisar inventario</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>🕒 Últimas ventas</h2>
        <?php if ($ultimas === []): ?>
            <p class="vacio">Todavía no hay ventas registradas.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Folio</th><th>Hora</th><th>Cajero</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($ultimas as $v): ?>
                    <tr>
                        <td><a href="ticket.php?id=<?= (int) $v['id'] ?>"><?= e($v['folio']) ?></a></td>
                        <td><?= e(date('d/m H:i', strtotime($v['created_at']))) ?></td>
                        <td><?= e($v['cajero']) ?></td>
                        <td><?= moneda((float) $v['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>

<section class="card ia-sugerencia-card">
    <h2>✨ Sugerencia de reposición</h2>
    <p class="ia-sugerencia-texto"><?= e($sugerencia['texto']) ?></p>
    <?php if ($sugerencia['productos'] !== []): ?>
        <ul class="ia-sugerencia-lista">
            <?php foreach ($sugerencia['productos'] as $p): ?>
                <li>
                    <strong><?= e($p['nombre']) ?></strong>
                    <span>Quedan <?= (int) $p['stock'] ?> · mínimo <?= (int) $p['minimo'] ?> · <?= e($p['motivo']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <div class="acciones">
        <a class="btn secondary" href="ia.php">Le pide más detalles a la IA</a>
    </div>
</section>

<section class="card">
    <h2>Accesos rápidos</h2>
    <div class="acciones-rapidas">
        <a class="btn success" href="pos.php">➕ Nueva venta</a>
        <?php if (($_SESSION['rol'] ?? '') === 'admin'): ?>
            <a class="btn primary" href="productos.php">📦 Productos</a>
            <a class="btn info" href="reportes.php">📊 Reportes</a>
        <?php endif; ?>
        <a class="btn secondary" href="ventas.php">🧾 Ver ventas</a>
    </div>
</section>
<?php renderFin(); ?>
