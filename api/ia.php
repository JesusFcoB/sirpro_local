<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/ia.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Inicia sesión para usar el asistente.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => 'El asistente IA todavía no está configurado: el administrador rellena includes/config_ia.php.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$datos = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($datos) || !csrfValido($datos['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['error' => 'Sesión inválida. Recarga la página e intenta de nuevo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$accion = (string) ($datos['accion'] ?? 'chat');

/** Sugerencia de reposición para el tablero (barata, sin modelo). */
if ($accion === 'sugerencia') {
    $sugerencia = iaSugerenciaLocal();
    echo json_encode([
        'respuesta'  => $sugerencia['texto'],
        'productos'  => $sugerencia['productos'],
        'proveedor'  => 'local',
        'modo'       => 'sugerencia',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Redactar descripción de producto desde el formulario de Productos. */
if ($accion === 'descripcion') {
    $nombreProducto = mb_substr(trim((string) ($datos['nombre'] ?? '')), 0, 150);
    $categoriaProducto = mb_substr(trim((string) ($datos['categoria'] ?? '')), 0, 80);
    $precioProducto = (string) ($datos['precio'] ?? '');
    $tipoProducto = ($datos['tipo'] ?? '') === 'servicio' ? 'servicio' : 'producto';

    if ($nombreProducto === '') {
        http_response_code(422);
        echo json_encode(['error' => 'Escribe el nombre del producto antes de generar la descripción.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $limiteDescripcion = iaVerificarLimite();
    if ($limiteDescripcion !== null) {
        http_response_code(429);
        echo json_encode(['error' => $limiteDescripcion], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mensajeProducto = 'Redacta una descripción breve, atractiva y gramaticalmente correcta para ' . $tipoProducto
        . ' llamado "' . $nombreProducto . '"'
        . ($categoriaProducto !== '' ? ' de la categoría ' . $categoriaProducto : '')
        . '. Corrige la ortografía y las tildes. Usa exclusivamente el nombre, tipo y categoría indicados: '
        . 'ignorá el catálogo del contexto y no agregues especificaciones técnicas que no aparezcan en el nombre. '
        . ($precioProducto !== '' ? 'Si mencionas el precio, usa exactamente ' . $precioProducto : 'No menciones precios.')
        . ' Máximo 300 caracteres, una sola línea, sin comillas, sin markdown y terminá con punto. Respondé solo la descripción.';

    try {
        $descripcion = iaLlamarIA($mensajeProducto, 'textos', []);
        echo json_encode([
            'descripcion' => iaLimpiarDescripcion($descripcion, 500),
            'proveedor'   => iaProveedor(),
            'modelo'      => iaModelo(),
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(503);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$mensaje = trim((string) ($datos['mensaje'] ?? ''));
$modo = (string) ($datos['modo'] ?? 'analisis');

if ($mensaje === '' || mb_strlen($mensaje) > 800) {
    http_response_code(422);
    echo json_encode(['error' => 'Escribe una pregunta de hasta 800 caracteres.'], JSON_UNESCAPED_UNICODE);
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
    echo json_encode(['error' => 'El administrador desactiva el envío de datos para el modo análisis.'], JSON_UNESCAPED_UNICODE);
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

$limite = iaVerificarLimite();
if ($limite !== null) {
    http_response_code(429);
    echo json_encode(['error' => $limite], JSON_UNESCAPED_UNICODE);
    exit;
}

$usarStream = !empty($datos['stream']);
$claveCache = ($modo !== 'analisis')
    ? iaCacheClave($modo, $mensaje, $historial)
    : '';
$enCache = $claveCache !== '' ? iaCacheLeer($claveCache) : null;

if ($usarStream) {
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');
    $enviar = static function (array $evento): void {
        echo 'data: ' . json_encode($evento, JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    };
    if ($enCache !== null) {
        $enviar(['tipo' => 'delta', 'texto' => $enCache]);
        $enviar(['tipo' => 'fin', 'respuesta' => $enCache, 'cache' => true, 'modelo' => iaModelo()]);
        exit;
    }
    try {
        $texto = iaLlamarIAStream(
            $mensaje,
            $modo,
            $historial,
            static function (string $delta) use ($enviar): void {
                $enviar(['tipo' => 'delta', 'texto' => $delta]);
            },
            static function () use ($enviar): void {
                $enviar(['tipo' => 'reset']);
            }
        );
        if ($claveCache !== '') {
            iaCacheGuardar($claveCache, $texto);
        }
        $enviar(['tipo' => 'fin', 'respuesta' => $texto, 'modelo' => iaModelo()]);
    } catch (Throwable $e) {
        $enviar(['tipo' => 'error', 'error' => $e->getMessage()]);
    }
    exit;
}

try {
    if ($enCache !== null) {
        $respuesta = $enCache;
    } else {
        $respuesta = iaLlamarIA($mensaje, $modo, $historial);
        if ($claveCache !== '') {
            iaCacheGuardar($claveCache, $respuesta);
        }
    }
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
