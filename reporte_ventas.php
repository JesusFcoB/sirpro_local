<?php
require __DIR__ . '/includes/bootstrap.php';
requerirAdmin();

$desde = $_GET['desde'] ?? date('Y-m-d');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
if (!strtotime($desde)) $desde = date('Y-m-d');
if (!strtotime($hasta)) $hasta = date('Y-m-d');

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT v.id, v.folio, v.total, v.metodo_pago, v.created_at, u.nombre AS cajero
     FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
     WHERE DATE(v.created_at) BETWEEN ? AND ?
     ORDER BY v.created_at DESC'
);
$stmt->execute([$desde, $hasta]);
$ventas = $stmt->fetchAll();
$totalPeriodo = array_sum(array_column($ventas, 'total'));

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ventas_' . $desde . '_a_' . $hasta . '.csv"');
    $salida = fopen('php://output', 'w');
    fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($salida, ['Folio', 'Fecha', 'Cajero', 'Método de pago', 'Total']);
    foreach ($ventas as $v) {
        fputcsv($salida, [
            $v['folio'],
            date('d/m/Y H:i', strtotime($v['created_at'])),
            $v['cajero'],
            $v['metodo_pago'],
            number_format((float) $v['total'], 2, '.', ''),
        ]);
    }
    fputcsv($salida, ['', '', '', 'TOTAL', number_format((float) $totalPeriodo, 2, '.', '')]);
    fclose($salida);
    exit;
}

render('Reporte de ventas', '');
?>
<div class="ticket-wrap" style="max-width: 1000px;">
    <div class="ticket" style="font-family: 'Segoe UI', Arial, sans-serif; font-size: 0.95rem;">
        <h2>Reporte de ventas</h2>
        <p>Periodo: <?= e($desde) ?> a <?= e($hasta) ?><br>Fecha de generación: <?= e(date('d/m/Y H:i')) ?></p>
        <table style="width: 100%;">
            <thead><tr><th>Folio</th><th>Fecha</th><th>Cajero</th><th>Método</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($ventas as $v): ?>
                <tr>
                    <td><?= e($v['folio']) ?></td>
                    <td><?= e(date('d/m/Y H:i', strtotime($v['created_at']))) ?></td>
                    <td><?= e($v['cajero']) ?></td>
                    <td><?= e($v['metodo_pago']) ?></td>
                    <td><?= moneda((float) $v['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: 12px;"><strong>Total del periodo: <?= moneda((float) $totalPeriodo) ?></strong></p>
    </div>
    <div class="acciones centrado">
        <button type="button" class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a class="btn primary" href="reportes.php">Volver</a>
    </div>
</div>
<?php renderFin(); ?>
