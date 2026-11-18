<?php
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function responder(int $codigo, array $data): never
{
    http_response_code($codigo);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responder(405, ['ok' => false, 'error' => 'Método no permitido.']);
}
if (empty($_SESSION['usuario_id'])) {
    responder(401, ['ok' => false, 'error' => 'Sesión expirada. Inicia sesión de nuevo.']);
}

$raw = file_get_contents('php://input');
$entrada = json_decode($raw === false ? '' : $raw, true, 32);
if (!is_array($entrada) || !csrfValido($entrada['csrf'] ?? null)) {
    responder(400, ['ok' => false, 'error' => 'Solicitud no válida. Recarga la página.']);
}

$items = $entrada['items'] ?? null;
$metodo = $entrada['metodo_pago'] ?? 'efectivo';

if (!is_array($items) || !array_is_list($items) || count($items) === 0) {
    responder(422, ['ok' => false, 'error' => 'La venta no tiene productos.']);
}
if (count($items) > 100) {
    responder(422, ['ok' => false, 'error' => 'Demasiados artículos en la venta.']);
}
if (!is_string($metodo) || !in_array($metodo, ['efectivo', 'tarjeta', 'transferencia'], true)) {
    responder(422, ['ok' => false, 'error' => 'Método de pago no válido.']);
}

$pagadoDeclarado = numeroValido($entrada['pagado'] ?? 0, 0, 1000000000);
if ($pagadoDeclarado === null) {
    responder(422, ['ok' => false, 'error' => 'El monto recibido no es válido.']);
}

$pdo = db();
try {
    $pdo->beginTransaction();

    $subtotal = 0.0;
    $detalles = [];
    $vistos = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('Artículo no válido.');
        }
        $productoId = idValido($item['producto_id'] ?? 0);
        $cantidad = filter_var((string) ($item['cantidad'] ?? ''), FILTER_VALIDATE_INT);
        $cantidad = ($cantidad !== false) ? (int) $cantidad : 0;

        if ($productoId <= 0 || $cantidad <= 0 || $cantidad > 1000) {
            throw new RuntimeException('Producto o cantidad no válidos.');
        }
        if (isset($vistos[$productoId])) {
            throw new RuntimeException('El mismo producto aparece dos veces en la venta.');
        }
        $vistos[$productoId] = true;

        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ? AND activo = 1 FOR UPDATE');
        $stmt->execute([$productoId]);
        $p = $stmt->fetch();
        if (!$p) {
            throw new RuntimeException('Un producto ya no está disponible.');
        }
        if ($p['tipo'] === 'producto' && (int) $p['stock'] < $cantidad) {
            throw new RuntimeException('Stock insuficiente de: ' . $p['nombre'] . ' (disponible: ' . $p['stock'] . ').');
        }

        $precio = $p['precio_oferta'] !== null ? (float) $p['precio_oferta'] : (float) $p['precio'];
        $importe = round($precio * $cantidad, 2);
        $subtotal += $importe;

        $detalles[] = [
            'producto_id' => $productoId,
            'nombre' => $p['nombre'],
            'precio' => $precio,
            'cantidad' => $cantidad,
            'importe' => $importe,
            'tipo' => $p['tipo'],
        ];
    }

    $total = round($subtotal, 2);
    $pagado = $metodo === 'efectivo' ? $pagadoDeclarado : $total;
    if ($metodo === 'efectivo' && $pagado < $total) {
        throw new RuntimeException('El monto recibido es menor al total.');
    }
    $cambio = $metodo === 'efectivo' ? round($pagado - $total, 2) : 0.0;

    $folio = 'V' . date('YmdHis') . random_int(100, 999);
    $stmt = $pdo->prepare(
        'INSERT INTO ventas (folio, usuario_id, subtotal, total, pagado, cambio, metodo_pago)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$folio, $_SESSION['usuario_id'], $total, $total, $pagado, $cambio, $metodo]);
    $ventaId = (int) $pdo->lastInsertId();

    $detalleStmt = $pdo->prepare(
        'INSERT INTO venta_detalles (venta_id, producto_id, nombre, precio_unitario, cantidad, subtotal)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stockStmt = $pdo->prepare('UPDATE productos SET stock = stock - ? WHERE id = ?');

    foreach ($detalles as $d) {
        $detalleStmt->execute([$ventaId, $d['producto_id'], $d['nombre'], $d['precio'], $d['cantidad'], $d['importe']]);
        if ($d['tipo'] === 'producto') {
            $stockStmt->execute([$d['cantidad'], $d['producto_id']]);
        }
    }

    $pdo->commit();
    responder(200, ['ok' => true, 'venta_id' => $ventaId, 'folio' => $folio]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(422, ['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responder(500, ['ok' => false, 'error' => 'Error interno al registrar la venta.']);
}
