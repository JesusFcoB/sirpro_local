<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/excel.php';
requerirAdmin();

$pdo = db();
$productos = $pdo->query(
    'SELECT p.sku, p.nombre, c.nombre AS categoria, p.tipo, p.descripcion,
            p.precio, p.precio_oferta, p.stock, p.stock_minimo
     FROM productos p
     JOIN categorias c ON c.id = p.categoria_id
     WHERE p.activo = 1
     ORDER BY c.nombre, p.nombre'
)->fetchAll();

$bajos = 0;
$agotados = 0;
foreach ($productos as $p) {
    if ($p['tipo'] !== 'producto') {
        continue;
    }
    $estado = estadoStock((int) $p['stock'], (int) ($p['stock_minimo'] ?? 5), 'producto');
    if ($estado === 'bajo') {
        $bajos++;
    } elseif ($estado === 'agotado') {
        $agotados++;
    }
}

$formato = $_GET['formato'] ?? 'pdf';

if ($formato === 'excel') {
    $filas = [];
    foreach ($productos as $p) {
        $minimo = (int) ($p['stock_minimo'] ?? 5);
        $estado = estadoStock((int) $p['stock'], $minimo, $p['tipo']);
        $filas[] = [
            excelCelda($p['sku']),
            excelCelda($p['nombre']),
            excelCelda($p['categoria']),
            excelCelda($p['tipo'] === 'servicio' ? 'Servicio' : 'Producto'),
            excelCelda($p['precio'], 'moneda'),
            excelCelda($p['precio_oferta'] ?? '', $p['precio_oferta'] !== null ? 'moneda' : 'texto'),
            excelCelda($p['stock'], 'numero'),
            excelCelda($minimo, 'numero'),
            excelCelda(etiquetaStock($estado)),
        ];
    }
    exportarExcel(
        'productos',
        'Reporte de productos',
        ['Generado' => date('d/m/Y H:i'), 'Productos activos' => count($productos)],
        ['SKU', 'Nombre', 'Categoría', 'Tipo', 'Precio', 'Oferta', 'Stock', 'Mínimo', 'Estado'],
        $filas,
        [95, 250, 160, 90, 100, 100, 75, 75, 100],
        [
            ['Productos activos', (string) count($productos)],
            ['Stock bajo', (string) $bajos],
            ['Agotados', (string) $agotados],
        ]
    );
}

render('Reporte de productos', '');
?>
<style>@page { size: A4 landscape; }</style>
<div class="reporte-pdf">
    <div class="hoja">
        <div class="hoja-cabecera">
            <img src="assets/logo.svg" alt="Sirpro">
            <div>
                <h2>Reporte de productos</h2>
                <p>Sirpro · Soluciones Profesionales</p>
            </div>
        </div>
        <div class="hoja-meta">
            <span><strong>Generado:</strong> <?= e(date('d/m/Y H:i')) ?></span>
            <span><strong>Productos:</strong> <?= count($productos) ?></span>
            <span><strong>Stock bajo:</strong> <?= $bajos ?></span>
            <span><strong>Agotados:</strong> <?= $agotados ?></span>
        </div>
        <table>
            <thead><tr><th>SKU</th><th>Nombre</th><th>Categoría</th><th>Tipo</th><th>Precio</th><th>Oferta</th><th>Stock</th><th>Mín.</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <?php
                    $minimo = (int) ($p['stock_minimo'] ?? 5);
                    $estado = estadoStock((int) $p['stock'], $minimo, $p['tipo']);
                ?>
                <tr>
                    <td><?= e($p['sku']) ?></td>
                    <td><?= e($p['nombre']) ?></td>
                    <td><?= e($p['categoria']) ?></td>
                    <td><?= $p['tipo'] === 'servicio' ? 'Servicio' : 'Producto' ?></td>
                    <td><?= moneda((float) $p['precio']) ?></td>
                    <td><?= $p['precio_oferta'] !== null ? moneda((float) $p['precio_oferta']) : '—' ?></td>
                    <td><?= (int) $p['stock'] ?></td>
                    <td><?= $minimo ?></td>
                    <td><span class="badge <?= $estado ?>"><?= e(etiquetaStock($estado)) ?></span></td>
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
