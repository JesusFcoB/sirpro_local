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
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
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

function render(string $titulo, string $contenido): void
{
    $nombre = e($_SESSION['nombre'] ?? '');
    $rol = $_SESSION['rol'] ?? '';
    $admin = $rol === 'admin'
        ? '<a href="pos.php">Punto de venta</a><a href="productos.php">Productos</a><a href="categorias.php">Categorías</a><a href="ventas.php">Ventas</a><a href="reportes.php">Reportes</a>'
        : '<a href="pos.php">Punto de venta</a><a href="ventas.php">Ventas</a>';
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titulo} - Sirpro</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="brand"><img src="assets/logo.png" alt="Sirpro" onerror="this.onerror=function(){this.style.display='none'};this.src='assets/logo.svg'"><span>Sirpro</span></div>
    <nav>
        <a href="pos.php">Punto de venta</a>
        {$admin}
    </nav>
    <div class="user">{$nombre} <a href="logout.php">Salir</a></div>
</header>
<main>
HTML;
}

function renderFin(): void
{
    echo "</main>\n</body>\n</html>\n";
}
