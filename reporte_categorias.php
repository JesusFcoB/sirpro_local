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
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="categorias_' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($salida, ['Categoría', 'Productos activos']);
    foreach ($categorias as $c) {
        fputcsv($salida, [$c['nombre'], (int) $c['total_productos']]);
    }
    fclose($salida);
    exit;
}

render('Reporte de categorías', '');
?>
<div class="ticket-wrap" style="max-width: 800px;">
    <div class="ticket" style="font-family: 'Segoe UI', Arial, sans-serif; font-size: 0.95rem;">
        <h2>Reporte de categorías</h2>
        <p>Fecha: <?= e(date('d/m/Y H:i')) ?></p>
        <table style="width: 100%;">
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
    </div>
    <div class="acciones centrado">
        <button type="button" class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>
        <a class="btn primary" href="reportes.php">Volver</a>
    </div>
</div>
<?php renderFin(); ?>
