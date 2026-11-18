<?php
require __DIR__ . '/includes/bootstrap.php';
requerirAdmin();

$pdo = db();
$categorias = $pdo->query(
    'SELECT c.nombre, COUNT(p.id) AS total_productos
     FROM categorias c
     LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
     GROUP BY c.id, c.nombre
     ORDER BY c.nombre'
)->fetchAll();

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="categorias_' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Segoe UI,Arial,sans-serif;font-size:11pt;}
        h1{color:#0f1f4d;border-bottom:2px solid #1d4ed8;padding-bottom:6px;}
        table{border-collapse:collapse;width:100%;}
        th{background:#1d4ed8;color:#fff;padding:8px;text-align:left;}
        td{border:1px solid #d6dde8;padding:6px;}
        tr:nth-child(even){background:#f1f5f9;}
    </style></head><body>';
    echo '<h1>Sirpro — Reporte de categorías</h1><p>Generado: ' . date('d/m/Y H:i') . '</p>';
    echo '<table><thead><tr><th>Categoría</th><th>Productos activos</th></tr></thead><tbody>';
    foreach ($categorias as $c) {
        echo '<tr><td>' . htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') . '</td><td>' . (int) $c['total_productos'] . '</td></tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

render('Reporte de categorías', '');
?>
<style>@page { size: A4 portrait; }</style>
<div class="reporte-pdf">
    <div class="hoja">
        <div class="hoja-cabecera">
            <img src="assets/logo.svg" alt="Sirpro">
            <div>
                <h2>Reporte de categorías</h2>
                <p>Sirpro · Soluciones Profesionales</p>
            </div>
        </div>
        <div class="hoja-meta">
            <span><strong>Generado:</strong> <?= e(date('d/m/Y H:i')) ?></span>
            <span><strong>Categorías:</strong> <?= count($categorias) ?></span>
        </div>
        <table>
            <thead><tr><th>Categoría</th><th>Productos activos</th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?= e($c['nombre']) ?></td>
                    <td><?= (int) $c['total_productos'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
