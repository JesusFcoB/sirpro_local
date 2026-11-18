<?php
require __DIR__ . '/includes/bootstrap.php';
requerirAdmin();

render('Reportes', '');
?>
<h1>Reportes</h1>
<p class="ayuta-filtro">Exporta en Excel con formato o abre una hoja lista para imprimir/guardar como PDF.</p>

<div class="card">
    <h2>📦 Productos</h2>
    <p>Listado completo de productos activos: SKU, nombre, categoría, tipo, precios y stock.</p>
    <div class="acciones">
        <a class="btn success" href="reporte_productos.php?formato=excel">Exportar Excel</a>
        <a class="btn danger" href="reporte_productos.php?formato=pdf" target="_blank">Abrir PDF</a>
    </div>
</div>

<div class="card">
    <h2>🏷️ Categorías</h2>
    <p>Listado de categorías con la cantidad de productos activos de cada una.</p>
    <div class="acciones">
        <a class="btn success" href="reporte_categorias.php?formato=excel">Exportar Excel</a>
        <a class="btn danger" href="reporte_categorias.php?formato=pdf" target="_blank">Abrir PDF</a>
    </div>
</div>

<div class="card">
    <h2>💰 Ventas</h2>
    <p>Selecciona el periodo y obtén el detalle de ventas con el total acumulado.</p>
    <form method="get" action="reporte_ventas.php" class="toolbar no-print">
        <label>Desde <input type="date" name="desde" value="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e(date('Y-m-d')) ?>" required></label>
        <input type="hidden" name="formato" value="excel">
        <button type="submit" class="btn success">Exportar Excel</button>
    </form>
    <form method="get" action="reporte_ventas.php" class="toolbar no-print">
        <label>Desde <input type="date" name="desde" value="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e(date('Y-m-d')) ?>" required></label>
        <input type="hidden" name="formato" value="pdf">
        <button type="submit" class="btn danger">Abrir PDF</button>
    </form>
</div>

<?php renderFin(); ?>
