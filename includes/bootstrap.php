<?php
declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'sirpro';
const DB_USER = 'root';
const DB_PASS = '';

session_start();

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            if (!headers_sent()) {
                http_response_code(503);
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
                . '<meta name="viewport" content="width=device-width, initial-scale=1">'
                . '<title>Sirpro - Base de datos no disponible</title>'
                . '<link rel="stylesheet" href="/assets/style.css"></head>'
                . '<body class="login-body"><div class="login-card" style="text-align:center">'
                . '<h1>Base de datos no disponible</h1>'
                . '<p>Sirpro no puede conectarse con MySQL. Iniciás MySQL desde el panel de XAMPP '
                . 'o ejecutás <code>iniciar_sirpro.bat</code> y recargás esta página.</p>'
                . '<a class="btn primary" href="javascript:location.reload()">Reintentar</a>'
                . '</div></body></html>';
            exit;
        }
    }
    return $pdo;
}

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function moneda(float $monto): string
{
    return '$' . number_format($monto, 2);
}

function requerirLogin(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requerirAdmin(): void
{
    requerirLogin();
    if (($_SESSION['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Acceso solo para administradores.');
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfValido(?string $token): bool
{
    return is_string($token) && isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

/** Campo oculto de CSRF listo para pegar en cualquier formulario. */
function csrfCampo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrfToken()) . '">';
}

/** Evita que un POST repetido con F5 vuelva a ejecutar la operacion. */
function irA(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

function flash(string $mensaje, string $tipo = 'ok'): void
{
    $_SESSION['flash'] = ['mensaje' => $mensaje, 'tipo' => $tipo === 'error' ? 'error' : 'ok'];
}

function tomarFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function renderMensajes(): void
{
    $flash = tomarFlash();
    if ($flash !== null) {
        echo '<p class="alert ' . $flash['tipo'] . '">' . e($flash['mensaje']) . '</p>';
    }
}

/** Entero positivo estricto; 0 cuando el dato no es valido. */
function idValido(mixed $valor): int
{
    if (is_int($valor)) {
        return $valor > 0 ? $valor : 0;
    }
    if (!is_string($valor) || trim($valor) === '') {
        return 0;
    }
    $numero = filter_var(trim($valor), FILTER_VALIDATE_INT);
    return ($numero !== false && $numero > 0) ? (int) $numero : 0;
}

/** Texto recortado y sin espacios sobrantes (evita campos desbordados). */
function textoValido(mixed $valor, int $max = 150): string
{
    $texto = is_string($valor) ? trim($valor) : '';
    return mb_substr($texto, 0, $max);
}

/** Numero decimal valido dentro de un rango; null si no lo es. */
function numeroValido(mixed $valor, float $min = 0, float $max = 1000000000): ?float
{
    if (!is_string($valor) && !is_int($valor) && !is_float($valor)) {
        return null;
    }
    $texto = trim((string) $valor);
    if ($texto === '' || !is_numeric($texto)) {
        return null;
    }
    $numero = (float) $texto;
    if (!is_finite($numero) || $numero < $min || $numero > $max) {
        return null;
    }
    return $numero;
}

/** Acepta unicamente fechas YYYY-MM-DD reales; si no, devuelve hoy. */
function fechaValida(mixed $valor): string
{
    $texto = is_string($valor) ? trim($valor) : '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
        $anio = (int) substr($texto, 0, 4);
        $mes = (int) substr($texto, 5, 2);
        $dia = (int) substr($texto, 8, 2);
        if (checkdate($mes, $dia, $anio)) {
            return $texto;
        }
    }
    return date('Y-m-d');
}

/** Estado de stock usado por tablero, inventario, punto de venta y reportes. */
function estadoStock(int $stock, int $minimo, string $tipo): string
{
    if ($tipo === 'servicio') {
        return 'servicio';
    }
    if ($stock <= 0) {
        return 'agotado';
    }
    return $stock <= max(0, $minimo) ? 'bajo' : 'ok';
}

function etiquetaStock(string $estado): string
{
    return match ($estado) {
        'agotado' => 'Agotado',
        'bajo' => 'Stock bajo',
        'servicio' => 'Servicio',
        default => 'Normal',
    };
}

function badgeStock(int $stock, int $minimo, string $tipo): string
{
    $estado = estadoStock($stock, $minimo, $tipo);
    $etiqueta = $estado === 'bajo' ? 'Bajo (' . $stock . ')' : etiquetaStock($estado);
    return '<span class="badge ' . $estado . '">' . e($etiqueta) . '</span>';
}

function render(string $titulo, string $contenido): void
{
    $nombre = e($_SESSION['nombre'] ?? '');
    $rol = $_SESSION['rol'] ?? '';
    $links = $rol === 'admin'
        ? '<a href="dashboard.php">Tablero</a><a href="pos.php">Punto de venta</a><a href="productos.php">Productos</a><a href="categorias.php">Categorías</a><a href="ventas.php">Ventas</a><a href="reportes.php">Reportes</a><a href="ia.php">Asistente IA</a>'
        : '<a href="dashboard.php">Tablero</a><a href="pos.php">Punto de venta</a><a href="ventas.php">Ventas</a><a href="ia.php">Asistente IA</a>';
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titulo} - Sirpro</title>
<link rel="stylesheet" href="assets/style.css">
<script>
function toggleDark(){
  document.body.classList.toggle('dark');
  localStorage.setItem('sirpro-dark', document.body.classList.contains('dark') ? '1' : '0');
  var b=document.getElementById('dark-btn'); if(b) b.textContent=document.body.classList.contains('dark')?'☀️':'🌙';
}
</script>
</head>
<body>
<script>
if (localStorage.getItem('sirpro-dark') === '1') document.body.classList.add('dark');
document.addEventListener('DOMContentLoaded', function(){
  if (document.body.classList.contains('dark')) {
    var b = document.getElementById('dark-btn');
    if (b) b.textContent = '☀️';
  }
});
</script>
<header class="topbar">
    <div class="brand"><img src="assets/logo.png" alt="Sirpro" onerror="this.onerror=function(){this.style.display='none'};this.src='assets/logo.svg'"><span>Sirpro</span></div>
    <nav>
        {$links}
    </nav>
    <div class="user"><button class="btn ghost mini" id="dark-btn" type="button" onclick="toggleDark()" title="Cambiar modo oscuro">🌙</button> {$nombre} <a class="btn ghost mini" href="logout.php">Salir</a></div>
</header>
<main>
HTML;
}

function renderFin(): void
{
    echo "</main>\n</body>\n</html>\n";
}
