<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/imagenes.php';
requerirAdmin();

$pdo = db();
$productos = $pdo->query(
    'SELECT p.sku, p.nombre, c.nombre AS categoria, p.tipo, p.descripcion,
            p.precio, p.precio_oferta, p.stock
     FROM productos p
     JOIN categorias c ON c.id = p.categoria_id
     WHERE p.activo = 1
     ORDER BY c.nombre, p.nombre'
)->fetchAll();

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d') . '.xls"');
    echo "\xEF\xBB\xBF";
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
        body{font-family:Segoe UI,Arial,sans-serif;font-size:12pt;}
        h1{color:#0f1f4d;border-bottom:2px solid #1d4ed8;padding-bottom:6px;}
        table{border-collapse:collapse;width:100%;}
        th{background:#1d4ed8;color:#fff;padding:8px;text-align:left;}
        td{border:1px solid #d6dde8;padding:6px;}
        tr:nth-child(even){background:#f1f5f9;}
    </style></head><body>';
    echo '<h1>Reporte de productos - Sirpro</h1>';
    echo '<p>Fecha: ' . date('d/m/Y H:i') . '</p>';
    echo '<table><thead><tr><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Tipo</th><th>Precio</th><th>Oferta</th><th>Stock</th></tr></thead><tbody>';
    foreach ($productos as $p) {
        $tipo = $p['tipo'] === 'servicio' ? 'Servicio' : 'Producto';
        $oferta = $p['precio_oferta'] !== null ? '$' . number_format((float) $p['precio_oferta'], 2) : '—';
        echo '<tr><td>' . htmlspecialchars($p['sku'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . htmlspecialchars($p['categoria'], ENT_QUOTES, 'UTF-8') . '</td>';
        echo '<td>' . $tipo . '</td>';
        echo '<td>$' . number_format((float) $p['precio'], 2) . '</td>';
        echo '<td>' . $oferta . '</td>';
        echo '<td>' . (int) $p['stock'] . '</td></tr>';
    }
    echo '</tbody></table></body></html>';
    exit;
}

render('Reporte de productos', '');
?>
<div class="ticket-wrap" style="max-width: 1000px;">
    <div class="ticket" style="font-family: 'Segoe UI', Arial, sans-serif; font-size: 0.95rem;">
        <h2>Reporte de productos</h2>
        <p>Fecha: <?= e(date('d/m/Y H:i')) ?></p>
        <table style="width: 100%; font-size: 0.85rem;">
            <thead><tr><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Tipo</th><th>Precio</th><th>Oferta</th><th>Stock</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td><?= e($p['sku']) ?></td>
                    <td><?= e($p['nombre']) ?></td>
                    <td><?= e($p['categoria']) ?></td>
                    <td><?= $p['tipo'] === 'servicio' ? 'Servicio' : 'Producto' ?></td>
                    <td><?= moneda((float) $p['precio']) ?></td>
                    <td><?= $p['precio_oferta'] !== null ? moneda((float) $p['precio_oferta']) : '—' ?></td>
                    <td><?= (int) $p['stock'] ?></td>
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
