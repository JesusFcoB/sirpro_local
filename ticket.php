<?php
require __DIR__ . '/includes/bootstrap.php';
requerirLogin();

$stmt = db()->prepare(
    'SELECT v.*, u.nombre AS cajero FROM ventas v
     JOIN usuarios u ON u.id = v.usuario_id WHERE v.id = ?'
);
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$venta = $stmt->fetch();

if (!$venta) {
    http_response_code(404);
    exit('Venta no encontrada.');
}

$detalles = db()->prepare('SELECT * FROM venta_detalles WHERE venta_id = ?');
$detalles->execute([$venta['id']]);
$detalles = $detalles->fetchAll();

render('Ticket ' . $venta['folio'], '');
?>
<div class="ticket-wrap">
    <div class="ticket" id="ticket">
        <div class="ticket-head">
            <img src="assets/logo.png" alt="Sirpro" onerror="this.onerror=function(){this.style.display='none'};this.src='assets/logo.svg'">
            <h2>Sirpro</h2>
            <p>Soluciones Profesionales</p>
        </div>
        <p>Folio: <strong><?= e($venta['folio']) ?></strong><br>
           Fecha: <?= e(date('d/m/Y H:i', strtotime($venta['created_at']))) ?><br>
           Atendió: <?= e($venta['cajero']) ?></p>
        <table>
            <thead><tr><th>Producto</th><th>Cant.</th><th>Importe</th></tr></thead>
            <tbody>
            <?php foreach ($detalles as $d): ?>
                <tr>
                    <td><?= e($d['nombre']) ?><small><?= moneda((float) $d['precio_unitario']) ?> c/u</small></td>
                    <td><?= (int) $d['cantidad'] ?></td>
                    <td><?= moneda((float) $d['subtotal']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="totales">
            <div class="total"><span>Total</span><strong><?= moneda((float) $venta['total']) ?></strong></div>
            <div><span>Pago (<?= e($venta['metodo_pago']) ?>)</span><strong><?= moneda((float) $venta['pagado']) ?></strong></div>
            <div><span>Cambio</span><strong><?= moneda((float) $venta['cambio']) ?></strong></div>
        </div>
        <p class="gracias">¡Gracias por su compra!</p>
    </div>
    <div class="acciones centrado">
        <button type="button" class="btn info no-print" onclick="window.print()">Imprimir</button>
        <a class="btn primary no-print" href="pos.php">Nueva venta</a>
    </div>
</div>
<?php renderFin(); ?>
