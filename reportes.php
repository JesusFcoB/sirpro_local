<?php
require __DIR__ . '/includes/bootstrap.php';
requerirAdmin();

render('Reportes', '');
?>
<h1>Reportes</h1>
<p class="ayuta-filtro">Exporta en Excel (CSV) o imprime en PDF desde el navegador.</p>

<div class="card">
    <h2>Productos</h2>
    <p>Listado completo de productos activos (sku, nombre, categoría, tipo, precio, oferta, stock, descripción).</p>
    <div class="acciones">
        <a class="btn" href="reporte_productos.php?formato=excel">Exportar Excel (CSV)</a>
        <a class="btn" href="reporte_productos.php?formato=pdf" target="_blank">Imprimir / Guardar PDF</a>
    </div>
</div>

<div class="card">
    <h2>Categorías</h2>
    <p>Listado de categorías con cantidad de productos por categoría.</p>
    <div class="acciones">
        <a class="btn" href="reporte_categorias.php?formato=excel">Exportar Excel (CSV)</a>
        <a class="btn" href="reporte_categorias.php?formato=pdf" target="_blank">Imprimir / Guardar PDF</a>
    </div>
</div>

<div class="card">
    <h2>Ventas</h2>
    <form method="get" action="reporte_ventas.php" class="toolbar">
        <label>Desde <input type="date" name="desde" value="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e(date('Y-m-d')) ?>" required></label>
        <input type="hidden" name="formato" value="excel">
        <button type="submit" class="btn">Exportar Excel (CSV)</button>
    </form>
    <form method="get" action="reporte_ventas.php" class="toolbar">
        <label>Desde <input type="date" name="desde" value="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Hasta <input type="date" name="hasta" value="<?= e(date('Y-m-d')) ?>" required></label>
        <input type="hidden" name="formato" value="pdf">
        <button type="submit" class="btn">Imprimir / Guardar PDF</button>
    </form>
</div>

<?php renderFin(); ?>
