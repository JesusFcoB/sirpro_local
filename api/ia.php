<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Iniciá sesión para usar el asistente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Consulta de estado: GET api/ia.php?estado=1 */
if (isset($_GET['estado'])) {
    echo json_encode(iaEstado(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!iaConfigurada()) {
    http_response_code(503);
    echo json_encode(['error' => 'El asistente IA todavía no está configurado. Pedile al administrador que rellene includes/config_ia.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$datos = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($datos) || !csrfValido($datos['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesión inválida. Recargá la página e intentá de nuevo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mensaje = trim((string) ($datos['mensaje'] ?? ''));
$modo = (string) ($datos['modo'] ?? 'analisis');

if ($mensaje === '' || mb_strlen($mensaje) > 800) {
    http_response_code(422);
    echo json_encode(['error' => 'Escribí una pregunta de hasta 800 caracteres.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!iaModoValido($modo)) {
    http_response_code(422);
    echo json_encode(['error' => 'Modo de asistente no válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($modo === 'analisis' && ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'El análisis de ventas e inventario está disponible solo para administradores.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($modo === 'analisis' && !iaIncluirDatos()) {
    http_response_code(403);
    echo json_encode(['error' => 'El administrador desactivó el envío de datos para el modo análisis.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$historial = [];
foreach (($datos['historial'] ?? []) as $item) {
    if (is_array($item)) {
        $historial[] = [
            'rol'       => $item['rol'] ?? '',
            'contenido' => mb_substr(trim((string) ($item['contenido'] ?? '')), 0, 1200),
        ];
    }
    if (count($historial) >= 8) {
        break;
    }
}

try {
    $respuesta = iaLlamarIA($mensaje, $modo, $historial);
    echo json_encode([
        'respuesta' => $respuesta,
        'proveedor' => iaProveedor(),
        'modelo'    => iaModelo(),
        'modo'      => $modo,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
