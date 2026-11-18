<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/excel.php';
requerirAdmin();

$desde = fechaValida($_GET['desde'] ?? date('Y-m-d'));
$hasta = fechaValida($_GET['hasta'] ?? date('Y-m-d'));
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT v.id, v.folio, v.total, v.metodo_pago, v.created_at, u.nombre AS cajero
     FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
     WHERE DATE(v.created_at) BETWEEN ? AND ?
     ORDER BY v.created_at DESC'
);
$stmt->execute([$desde, $hasta]);
$ventas = $stmt->fetchAll();
$totalPeriodo = (float) array_sum(array_column($ventas, 'total'));

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'excel') {
    $filas = [];
    foreach ($ventas as $v) {
        $filas[] = [
            excelCelda($v['folio']),
            excelCelda(date('d/m/Y H:i', strtotime($v['created_at']))),
            excelCelda($v['cajero']),
            excelCelda($v['metodo_pago']),
            excelCelda($v['total'], 'moneda'),
        ];
    }
    exportarExcel(
        'ventas_' . $desde . '_a_' . $hasta,
        'Reporte de ventas',
        ['Periodo' => $desde . ' a ' . $hasta, 'Generado' => date('d/m/Y H:i'), 'Ventas' => count($ventas)],
        ['Folio', 'Fecha', 'Cajero', 'Método', 'Total'],
        $filas,
        [130, 150, 170, 120, 120],
        [['Ventas', (string) count($ventas)], ['Total del periodo', moneda($totalPeriodo)]]
    );
}

render('Reporte de ventas', '');
?>
<style>@page { size: A4 landscape; }</style>
<div class="reporte-pdf">
    <div class="hoja">
        <div class="hoja-cabecera">
            <img src="assets/logo.svg" alt="Sirpro">
            <div>
                <h2>Reporte de ventas</h2>
                <p>Sirpro · Soluciones Profesionales</p>
            </div>
        </div>
        <div class="hoja-meta">
            <span><strong>Periodo:</strong> <?= e($desde) ?> a <?= e($hasta) ?></span>
            <span><strong>Generado:</strong> <?= e(date('d/m/Y H:i')) ?></span>
            <span><strong>Ventas:</strong> <?= count($ventas) ?></span>
        </div>
        <table>
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
        <div class="hoja-resumen"><div>Total del periodo: <?= moneda($totalPeriodo) ?></div></div>
        <div class="hoja-pie">
            <span>Documento generado por Sirpro · Punto de venta</span>
            <span>Página 1</span>
        </div>
    </div>
    <div class="acciones centrado no-print">
        <button type="button" class="btn primary" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a class="btn secondary" href="reportes.php">Volver</a>
    </div>
</div>
<?php renderFin(); ?>
