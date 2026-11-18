<?php
require __DIR__ . '/includes/bootstrap.php';
requerirLogin();

$desde = fechaValida($_GET['desde'] ?? date('Y-m-d'));
$hasta = fechaValida($_GET['hasta'] ?? date('Y-m-d'));
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$stmt = db()->prepare(
    'SELECT v.id, v.folio, v.total, v.metodo_pago, v.created_at, u.nombre AS cajero
     FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
     WHERE DATE(v.created_at) BETWEEN ? AND ?
     ORDER BY v.created_at DESC'
);
$stmt->execute([$desde, $hasta]);
$ventas = $stmt->fetchAll();
$totalPeriodo = array_sum(array_column($ventas, 'total'));

render('Ventas', '');
?>
<h1>Ventas</h1>
<form method="get" class="toolbar">
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <button type="submit" class="btn info">Filtrar</button>
</form>

<p class="resumen">Ventas: <strong><?= count($ventas) ?></strong> · Total: <strong><?= moneda((float) $totalPeriodo) ?></strong></p>

<table class="tabla">
    <thead>
        <tr><th>Folio</th><th>Fecha</th><th>Cajero</th><th>Pago</th><th>Total</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($ventas as $v): ?>
        <tr>
            <td><?= e($v['folio']) ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime($v['created_at']))) ?></td>
            <td><?= e($v['cajero']) ?></td>
            <td><?= e($v['metodo_pago']) ?></td>
            <td><?= moneda((float) $v['total']) ?></td>
            <td><a href="ticket.php?id=<?= (int) $v['id'] ?>">Ver ticket</a></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$ventas): ?>
        <tr><td colspan="6" class="vacio">No hay ventas en este periodo.</td></tr>
    <?php endif; ?>
    </tbody>
</table>
<?php renderFin(); ?>
