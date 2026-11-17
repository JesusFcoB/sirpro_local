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
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d') . '.csv"');
    $salida = fopen('php://output', 'w');
    fprintf($salida, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM para Excel
    fputcsv($salida, ['SKU', 'Nombre', 'Categoría', 'Tipo', 'Descripción', 'Precio', 'Precio oferta', 'Stock']);
    foreach ($productos as $p) {
        fputcsv($salida, [
            $p['sku'],
            $p['nombre'],
            $p['categoria'],
            $p['tipo'] === 'servicio' ? 'Servicio' : 'Producto',
            $p['descripcion'],
            number_format((float) $p['precio'], 2, '.', ''),
            $p['precio_oferta'] !== null ? number_format((float) $p['precio_oferta'], 2, '.', '') : '',
            $p['stock'],
        ]);
    }
    fclose($salida);
    exit;
}

// Formato PDF: vista imprimible
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
