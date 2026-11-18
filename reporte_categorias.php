<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/excel.php';
requerirAdmin();

$pdo = db();
$categorias = $pdo->query(
    'SELECT c.nombre, COUNT(p.id) AS total_productos,
            COALESCE(SUM(p.stock * COALESCE(p.precio_oferta, p.precio)), 0) AS valor
     FROM categorias c
     LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
     GROUP BY c.id, c.nombre
     ORDER BY c.nombre'
)->fetchAll();

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'excel') {
    $filas = [];
    foreach ($categorias as $c) {
        $filas[] = [
            excelCelda($c['nombre']),
            excelCelda($c['total_productos'], 'numero'),
            excelCelda($c['valor'], 'moneda'),
        ];
    }
    exportarExcel(
        'categorias',
        'Reporte de categorías',
        ['Generado' => date('d/m/Y H:i'), 'Categorías' => count($categorias)],
        ['Categoría', 'Productos activos', 'Valor de inventario'],
        $filas,
        [260, 150, 180],
        [['Categorías', (string) count($categorias)]]
    );
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
            <thead><tr><th>Categoría</th><th>Productos activos</th><th>Valor de inventario</th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?= e($c['nombre']) ?></td>
                    <td><?= (int) $c['total_productos'] ?></td>
                    <td><?= moneda((float) $c['valor']) ?></td>
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
