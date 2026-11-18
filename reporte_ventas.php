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
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="ventas_' . $desde . '_a_' . $hasta . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Segoe UI,Arial,sans-serif;font-size:11pt;}
        h1{color:#0f1f4d;border-bottom:2px solid #1d4ed8;padding-bottom:6px;}
        table{border-collapse:collapse;width:100%;}
        th{background:#1d4ed8;color:#fff;padding:8px;text-align:left;}
        td{border:1px solid #d6dde8;padding:6px;}
        tr:nth-child(even){background:#f1f5f9;}
        .total{font-weight:bold;background:#eff6ff;}
    </style></head><body>';
    echo '<h1>Sirpro — Reporte de ventas</h1><p>Periodo: ' . htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') . ' a ' . htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') . '</p>';
    echo '<table><thead><tr><th>Folio</th><th>Fecha</th><th>Cajero</th><th>Método</th><th>Total</th></tr></thead><tbody>';
    foreach ($ventas as $v) {
        echo '<tr><td>' . htmlspecialchars($v['folio'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . date('d/m/Y H:i', strtotime($v['created_at'])) . '</td>';
        echo '<td>' . htmlspecialchars($v['cajero'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($v['metodo_pago'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>$' . number_format((float) $v['total'], 2) . '</td></tr>';
    }
    echo '</tbody><tfoot><tr class="total"><td colspan="4">TOTAL DEL PERIODO</td><td>$' . number_format((float) $totalPeriodo, 2) . '</td></tr></tfoot></table></body></html>';
    exit;
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
        <div class="hoja-resumen"><div>Total del periodo: <?= moneda((float) $totalPeriodo) ?></div></div>
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
